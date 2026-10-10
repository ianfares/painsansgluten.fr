<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\DeliveryMethod;
use App\Models\Category;
use App\Models\Product;
use App\Services\Reporting\ProductionPlan;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Page « À produire » (T27-L9) : quantités à fabriquer (commandes payées ou en
 * préparation) sur une plage de dates d'expédition prévue. Calculs :
 * App\Services\Reporting\ProductionPlan. Export PDF sans prix.
 */
class ProductionToDo extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Commandes';

    protected static ?string $navigationLabel = 'À produire';

    protected static ?string $title = 'À produire';

    protected static ?string $slug = 'a-produire';

    protected static string $view = 'filament.pages.production-to-do';

    /** @var array<string, mixed> */
    public array $data = [];

    public string $sortBy = 'planned_ship_date';

    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->data = [
            'from' => today()->toDateString(),
            'to' => today()->addDays(7)->toDateString(),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    DatePicker::make('from')->label('Expédition prévue du')->live()->native(false)->displayFormat('d/m/Y'),
                    DatePicker::make('to')->label('au')->live()->native(false)->displayFormat('d/m/Y'),
                    Select::make('delivery_methods')
                        ->label('Mode de livraison')
                        ->multiple()
                        ->options(collect(DeliveryMethod::cases())->mapWithKeys(fn (DeliveryMethod $m) => [$m->value => $m->label()])->all())
                        ->live(),
                    Select::make('categories')
                        ->label('Catégorie')
                        ->multiple()
                        ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->live(),
                    Select::make('products')
                        ->label('Produit')
                        ->multiple()
                        ->searchable()
                        ->columnSpan(['md' => 2])
                        ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->live(),
                ]),
            ]);
    }

    public function sort(string $column): void
    {
        if (! in_array($column, ProductionPlan::SORTABLE, true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    public function plan(): ProductionPlan
    {
        return ProductionPlan::fromFilters($this->data);
    }

    public function exportPdf(): StreamedResponse
    {
        $plan = $this->plan();

        $pdf = Pdf::loadView('pdf.production-plan', [
            'filters' => $plan->filterSummary(),
            'totals' => $plan->totalsByProduct(),
            'details' => $plan->details($this->sortBy, $this->sortDirection),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf->output();
            },
            'a-produire-'.now()->format('Y-m-d').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
