<?php
// Activer le mode test ou production
define("MODE_TEST", true); // Passez à `false` pour le mode production

// Configuration de PayZen
define("PAYZEN_SITE_ID", "22614374");
define("PAYZEN_TEST_KEY", "kmcSPrxumxxwauab");
define("PAYZEN_PRODUCTION_KEY", "qMSa6ozZokLUNKnB");
define("PAYZEN_URL", "https://secure.osb.pf/vads-payment/");

// Fonction de logging
function writeLog($message) {
    $logFile = __DIR__ . '/payment_log.txt';
    $currentDate = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$currentDate] $message\n", FILE_APPEND);
}

// Début du script
writeLog("Script de redirection vers PayZen démarré. Mode : " . (MODE_TEST ? "Test" : "Production"));

// Récupérer les données reçues pour la transaction
$data = $_POST ?: $_GET;
writeLog("Données reçues : " . print_r($data, true));

// Vérification du propid et autres données essentielles
$propertyId = $data['propid'] ?? null;
if (!$propertyId) {
    writeLog("Erreur : Propriété ID (propid) manquante.");
    die("Erreur : Propriété ID manquante.");
}

$keys = include('keys.php');
if (!isset($keys[$propertyId])) {
    writeLog("Erreur : Propriété non trouvée pour propid : $propertyId");
    die("Erreur : Propriété non trouvée.");
}
writeLog("Propriété trouvée pour propid : $propertyId");

// Autres données essentielles
$bookId = $data['bookid'] ?? null;
$amountEUR = $data['amount'] ?? null;
$description = $data['description'] ?? null;

if (!$bookId || !$amountEUR || !$description) {
    writeLog("Erreur : Données essentielles manquantes. bookId: $bookId, amountEUR: $amountEUR, description: $description");
    die("Erreur : Données essentielles manquantes.");
}

// Conversion du montant en XPF
$taux_conversion = 119.33;
$amountXPF = round($amountEUR * $taux_conversion);
writeLog("Montant en EUR : $amountEUR - Montant en XPF : $amountXPF");

// Clé pour la signature
$secret_key = MODE_TEST ? PAYZEN_TEST_KEY : PAYZEN_PRODUCTION_KEY;

// Préparer les données pour PayZen
$payzenData = [
    "vads_action_mode" => "INTERACTIVE",
    "vads_amount" => $amountXPF,
    "vads_ctx_mode" => MODE_TEST ? "TEST" : "PRODUCTION",
    "vads_currency" => "953",
    "vads_order_id" => $bookId,
    "vads_order_info" => $description,
    "vads_page_action" => "PAYMENT",
    "vads_payment_config" => "SINGLE",
    "vads_site_id" => PAYZEN_SITE_ID,
    "vads_trans_date" => gmdate("YmdHis"),
    "vads_trans_id" => strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
    "vads_version" => "V2",
    "vads_ext_info_propid" => $propertyId // Ajout de propid comme paramètre personnalisé
];

// Générer la signature pour PayZen
ksort($payzenData);
$signatureString = implode("+", array_map('strval', $payzenData)) . "+" . $secret_key;
$signature = base64_encode(hash_hmac('sha256', $signatureString, $secret_key, true));
$payzenData["signature"] = $signature;

// Log détaillé de la génération de la signature
writeLog("Chaîne de signature attendue par PayZen : " . $signatureString);
writeLog("Signature générée : " . $signature);

// Vérification des données de redirection
writeLog("Données de redirection PayZen : " . print_r($payzenData, true));

// Génération du formulaire HTML pour la redirection
echo '<html><body onload="document.forms[\'payzenForm\'].submit();">';
echo '<form name="payzenForm" action="' . PAYZEN_URL . '" method="POST">';
foreach ($payzenData as $key => $value) {
    echo '<input type="hidden" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
}
echo '</form></body></html>';

// Confirmation de la redirection
writeLog("Formulaire HTML de redirection généré.");
writeLog("Script de redirection vers PayZen terminé.");
?>
