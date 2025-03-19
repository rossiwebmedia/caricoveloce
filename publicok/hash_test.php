<?php
// File hash_test.php
$plainTextPassword = 'password123';

// Genera un nuovo hash
$newHash = password_hash($plainTextPassword, PASSWORD_DEFAULT);
echo "Nuovo hash generato: " . $newHash . "<br>";

// Verifica con l'hash generato al momento
$verifyNew = password_verify($plainTextPassword, $newHash);
echo "Verifica con hash appena generato: " . ($verifyNew ? "SÌ" : "NO") . "<br>";

// Verifica con l'hash noto
$knownHash = '$2y$10$YwJzDXkWh4QDkmYhQPtpLOfuUKKMQSGzGfHq3.ATCxPx3HJRLrL1y';
$verifyKnown = password_verify($plainTextPassword, $knownHash);
echo "Verifica con hash noto: " . ($verifyKnown ? "SÌ" : "NO") . "<br>";

// Informazioni sulla versione PHP
echo "<br>Versione PHP: " . phpversion() . "<br>";
echo "Algoritmi di hash disponibili: " . implode(", ", password_algos());
?>