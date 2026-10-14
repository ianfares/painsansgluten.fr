<?php

declare(strict_types=1);

namespace App\Services\Security;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Détection d'intrusion par empreintes SHA-256 (demande de Ian, 14/10/2026).
 *
 * `snapshot()` photographie le code sain au déploiement ; `check()` le
 * compare à l'état actuel et cherche des fichiers exécutables dans les
 * dossiers d'upload (porte dérobée classique). Les liens symboliques ne sont
 * jamais suivis (public/storage pointe vers les uploads).
 */
class FileIntegrity
{
    /** Extensions exécutables par PHP ou capables de changer la config Apache. */
    private const SUSPICIOUS = '/(\.(php\d?|phtml|phar|pht|phps|inc|cgi|pl|py|sh)$|^\.htaccess$|^\.user\.ini$)/i';

    /**
     * @return int nombre de fichiers enregistrés
     */
    public function snapshot(): int
    {
        $hashes = $this->hashWatched();
        $manifest = (string) config('security.integrity.manifest');

        File::ensureDirectoryExists(dirname($manifest), 0700);
        File::put($manifest, (string) json_encode(['created_at' => now()->toIso8601String(), 'files' => $hashes], JSON_UNESCAPED_SLASHES));
        @chmod($manifest, 0600);
        $this->forgetAlert();

        return count($hashes);
    }

    /**
     * Une seule alerte par anomalie (demande de Ian) : vrai si cette même
     * anomalie (mêmes fichiers) a déjà été signalée.
     *
     * @param  array{missing_manifest: bool, modified: list<string>, added: list<string>, removed: list<string>, suspicious: list<string>}  $result
     */
    public function alreadyAlerted(array $result): bool
    {
        $file = $this->alertFile();

        return is_file($file) && hash_equals((string) file_get_contents($file), $this->fingerprint($result));
    }

    /**
     * @param  array{missing_manifest: bool, modified: list<string>, added: list<string>, removed: list<string>, suspicious: list<string>}  $result
     */
    public function rememberAlert(array $result): void
    {
        $file = $this->alertFile();

        File::ensureDirectoryExists(dirname($file), 0700);
        File::put($file, $this->fingerprint($result));
        @chmod($file, 0600);
    }

    /** Situation revenue à la normale (ou nouvelle référence) : la prochaine anomalie sera signalée. */
    public function forgetAlert(): void
    {
        File::delete($this->alertFile());
    }

    /**
     * @return array{missing_manifest: bool, modified: list<string>, added: list<string>, removed: list<string>, suspicious: list<string>}
     */
    public function check(): array
    {
        $result = ['missing_manifest' => false, 'modified' => [], 'added' => [], 'removed' => [], 'suspicious' => $this->suspiciousUploads()];
        $manifest = (string) config('security.integrity.manifest');

        $reference = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;
        if (! is_array($reference) || ! is_array($reference['files'] ?? null)) {
            $result['missing_manifest'] = true;

            return $result;
        }

        /** @var array<string, string> $expected */
        $expected = $reference['files'];
        $current = $this->hashWatched();

        foreach ($current as $path => $hash) {
            if (! array_key_exists($path, $expected)) {
                $result['added'][] = $path;
            } elseif (! hash_equals($expected[$path], $hash)) {
                $result['modified'][] = $path;
            }
        }
        $result['removed'] = array_map('strval', array_keys(array_diff_key($expected, $current)));

        return $result;
    }

    /**
     * @param  array{missing_manifest: bool, modified: list<string>, added: list<string>, removed: list<string>, suspicious: list<string>}  $result
     */
    public static function hasProblems(array $result): bool
    {
        return $result['missing_manifest'] || $result['modified'] !== [] || $result['added'] !== []
            || $result['removed'] !== [] || $result['suspicious'] !== [];
    }

    /**
     * @param  array{missing_manifest: bool, modified: list<string>, added: list<string>, removed: list<string>, suspicious: list<string>}  $result
     */
    private function fingerprint(array $result): string
    {
        return hash('sha256', (string) json_encode($result));
    }

    private function alertFile(): string
    {
        return dirname((string) config('security.integrity.manifest')).'/last-alert.sha256';
    }

    /**
     * @return array<string, string> chemin relatif => SHA-256
     */
    private function hashWatched(): array
    {
        $hashes = [];

        foreach ((array) config('security.integrity.watched') as $entry) {
            $path = $this->absolute((string) $entry);

            if (is_link($path)) {
                continue;
            }
            if (is_file($path)) {
                $hashes[$this->relative($path)] = $this->hash($path);
            } elseif (is_dir($path)) {
                foreach ($this->files($path) as $file) {
                    $hashes[$this->relative($file->getPathname())] = $this->hash($file->getPathname());
                }
            }
        }

        ksort($hashes, SORT_STRING);

        return $hashes;
    }

    /**
     * @return list<string>
     */
    private function suspiciousUploads(): array
    {
        $found = [];

        foreach ((array) config('security.integrity.uploads') as $entry) {
            $path = $this->absolute((string) $entry);
            if (! is_dir($path) || is_link($path)) {
                continue;
            }
            foreach ($this->files($path) as $file) {
                if (preg_match(self::SUSPICIOUS, $file->getFilename()) === 1) {
                    $found[] = $this->relative($file->getPathname());
                }
            }
        }

        sort($found, SORT_STRING);

        return $found;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function files(string $directory): iterable
    {
        // Sans FOLLOW_SYMLINKS : un lien n'est jamais parcouru.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && ! $file->isLink()) {
                yield $file;
            }
        }
    }

    private function hash(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException('Fichier illisible : '.$this->relative($path));
        }

        return $hash;
    }

    private function absolute(string $relative): string
    {
        return rtrim((string) config('security.integrity.root'), '/').'/'.ltrim($relative, '/');
    }

    private function relative(string $absolute): string
    {
        return ltrim(substr($absolute, strlen(rtrim((string) config('security.integrity.root'), '/'))), '/');
    }
}
