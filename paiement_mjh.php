<?php
// Activer le mode test ou production
define("MODE_TEST", true); // Passez � `false` pour le mode production

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

// D�but du script
writeLog("Script de redirection vers PayZen d�marr�. Mode : " . (MODE_TEST ? "Test" : "Production"));

// R�cup�rer les donn�es re�ues pour la transaction
$data = $_POST ?: $_GET;
writeLog("Donn�es re�ues : " . print_r($data, true));

// V�rification du propid et autres donn�es essentielles
$propertyId = $data['propid'] ?? null;
if (!$propertyId) {
    writeLog("Erreur : Propri�t� ID (propid) manquante.");
    die("Erreur : Propri�t� ID manquante.");
}

$keys = include('keys.php');
if (!isset($keys[$propertyId])) {
    writeLog("Erreur : Propri�t� non trouv�e pour propid : $propertyId");
    die("Erreur : Propri�t� non trouv�e.");
}
writeLog("Propri�t� trouv�e pour propid : $propertyId");

// Autres donn�es essentielles
$bookId = $data['bookid'] ?? null;
$amountEUR = $data['amount'] ?? null;
$description = $data['description'] ?? null;

if (!$bookId || !$amountEUR || !$description) {
    writeLog("Erreur : Donn�es essentielles manquantes. bookId: $bookId, amountEUR: $amountEUR, description: $description");
    die("Erreur : Donn�es essentielles manquantes.");
}

// Conversion du montant en XPF
$taux_conversion = 119.33;
$amountXPF = round($amountEUR * $taux_conversion);
writeLog("Montant en EUR : $amountEUR - Montant en XPF : $amountXPF");

// Cl� pour la signature
$secret_key = MODE_TEST ? PAYZEN_TEST_KEY : PAYZEN_PRODUCTION_KEY;

// Pr�parer les donn�es pour PayZen
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
    "vads_ext_info_propid" => $propertyId // Ajout de propid comme param�tre personnalis�
];

// G�n�rer la signature pour PayZen
ksort($payzenData);
$signatureString = implode("+", array_map('strval', $payzenData)) . "+" . $secret_key;
$signature = base64_encode(hash_hmac('sha256', $signatureString, $secret_key, true));
$payzenData["signature"] = $signature;

// Log d�taill� de la g�n�ration de la signature
writeLog("Cha�ne de signature attendue par PayZen : " . $signatureString);
writeLog("Signature g�n�r�e : " . $signature);

// V�rification des donn�es de redirection
writeLog("Donn�es de redirection PayZen : " . print_r($payzenData, true));

// G�n�ration du formulaire HTML pour la redirection
echo '<html><body onload="document.forms[\'payzenForm\'].submit();">';
echo '<form name="payzenForm" action="' . PAYZEN_URL . '" method="POST">';
foreach ($payzenData as $key => $value) {
    echo '<input type="hidden" name="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
}
echo '</form></body></html>';

// Confirmation de la redirection
writeLog("Formulaire HTML de redirection g�n�r�.");
writeLog("Script de redirection vers PayZen termin�.");
?>
