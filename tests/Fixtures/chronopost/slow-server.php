<?php

declare(strict_types=1);

// Faux Chronopost très lent (test du délai d'attente) : répond après 5 s.
sleep(5);
header('Content-Type: text/xml');
echo '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body/></soap:Envelope>';
