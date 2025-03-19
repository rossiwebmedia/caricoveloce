<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.gestionalesmarty.com/titanium/V2/Api/Products/list?ApiKey=0b73f8034520604c7dfd631558463dcde99786db1ac0ef644817348dc89360e564a23f1cf9cd7add492b218911a726089a34ae33d5ec8188619016bd04a3998e");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "Errore cURL: " . $error;
} else {
    echo "Risposta API: " . $response;
}
?>