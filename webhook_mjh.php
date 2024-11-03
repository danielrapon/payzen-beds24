<?php
// Activer le mode test ou production
define("MODE_TEST", true); // `true` pour le mode test

// Fonction de logging détaillée
function writeLog($message) {
    $logFile = __DIR__ . '/webhook_log.txt';
    $currentDate = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$currentDate] $message\n"); // Écrase les logs précédents à chaque exécution
}

writeLog("Webhook PayZen démarré.");

// Récupérer les données envoyées par PayZen
$data = $_POST;
writeLog("Données reçues de PayZen : " . print_r($data, true));

// Vérification de la présence de propid
$propertyId = $data['vads_ext_info_propid'] ?? null;
if (!$propertyId) {
    writeLog("Erreur : Propriété ID (propid) non reçue dans les données POST.");
    die("Erreur : Propriété ID manquante.");
}

// Inclure le fichier keys.php pour obtenir les clés
$keys = include('keys.php');
if (!isset($keys[$propertyId])) {
    writeLog("Erreur : Propriété non trouvée pour propid : $propertyId");
    die("Erreur : Propriété non trouvée.");
}

writeLog("Propriété trouvée pour propid : $propertyId");

// Récupération des clés API
$apiKey = $keys[$propertyId]['apiKey'];
$propKey = $keys[$propertyId]['propKey'];

// Conversion du montant en EUR
$taux_conversion = 119.33;
$montant_en_xpf = $data['vads_amount'];
$montant_en_eur = round($montant_en_xpf / $taux_conversion, 2);
writeLog("Montant converti en EUR pour Beds24 : $montant_en_eur");

// Vérification du statut de paiement
$paymentApproved = ($data['vads_result'] === "00");

// Préparation des données d'infocode
$infocode_data = [
    "authentication" => [
        "apiKey" => $apiKey,
        "propKey" => $propKey
    ],
    "bookId" => $data['vads_order_id'],
    "infoItems" => [
        [
            "code" => "PAYZENPAYMENT",
            "text" => $paymentApproved ? "Payment {$montant_en_eur} EUR - Transaction : {$data['vads_trans_id']}" : "Paiement non valide"
        ],
        [
            "code" => "PAYE",
            "text" => $paymentApproved ? "Payé" : "Non payé"
        ]
    ]
];

// Encodage JSON avec vérification
$infocode_json = json_encode($infocode_data, JSON_UNESCAPED_UNICODE);
if (json_last_error() !== JSON_ERROR_NONE) {
    writeLog("Erreur JSON lors de l'encodage des infoItems : " . json_last_error_msg());
} else {
    writeLog("Données infoItems envoyées à Beds24 : $infocode_json");

    // Envoi de la requête infocode
    $ch = curl_init("https://api.beds24.com/json/setBooking");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $infocode_json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    $info_response = curl_exec($ch);
    $info_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $info_error_msg = curl_error($ch);
    curl_close($ch);

    if ($info_response === false || $info_http_code !== 200) {
        writeLog("Erreur lors de l'ajout des infocodes à Beds24. Code HTTP : $info_http_code - Message : $info_error_msg - Réponse : $info_response");
    } else {
        writeLog("Infocodes ajoutés avec succès à Beds24. Réponse : $info_response");
    }
}

// Préparation des données pour la notification de paiement
$payment_data = [
    "key" => MODE_TEST ? "kmcSPrxumxxwauab" : "qMSa6ozZokLUNKnB",
    "bookId" => $data['vads_order_id'],
    "status" => $paymentApproved ? "1" : "0",
    "amount" => $montant_en_eur,
    "description" => $data['vads_order_info'],
    "payment_status" => $paymentApproved ? "Paid" : "Unpaid",
    "txnid" => $data['vads_trans_id']
];

$payment_json = http_build_query($payment_data);
writeLog("Données de notification envoyées à Beds24 : $payment_json");

$ch = curl_init("https://api.beds24.com/custompaymentgateway/notify.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payment_json);
$payment_response = curl_exec($ch);
$payment_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$payment_error_msg = curl_error($ch);
curl_close($ch);

if ($payment_response === false || $payment_http_code !== 200) {
    writeLog("Erreur lors de la notification de paiement à Beds24. Code HTTP : $payment_http_code - Message : $payment_error_msg - Réponse : $payment_response");
} else {
    writeLog("Notification de paiement envoyée avec succès à Beds24. Réponse : $payment_response");
}

writeLog("Webhook PayZen terminé.");
?>
