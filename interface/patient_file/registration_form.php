<?php
$fake_register_globals = false;
$sanitize_all_escapes = true;

use setasign\Fpdi\Fpdi;

require_once("../globals.php");
require_once("$srcdir/fpdf/fpdf.php");
require_once("$srcdir/fpdi/autoload.php");
require_once("$srcdir/formatting.inc.php");
require_once("$srcdir/classes/php-barcode.php");

function addText($pdf, $coord, $text)
{
    $pdf->SetXY($coord['x'], $coord['y']);
    $pdf->Cell($coord['w'], $coord['h'], $text);
}

// store the positions of the fields
$coords['lname'] = ['x' => 78, 'y' => 64, 'w' => 119, 'h' => 4];
$coords['fname'] = ['x' => 78, 'y' => 75, 'w' => 119, 'h' => 4];
$coords['address1'] = ['x' => 13, 'y' => 89, 'w' => 184, 'h' => 4];
$coords['address2'] = ['x' => 13, 'y' => 101, 'w' => 127, 'h' => 4];
$coords['postal_code'] = ['x' => 143, 'y' => 101, 'w' => 54, 'h' => 4];

// contact details
$coords['phone_home'] = ['x' => 13, 'y' => 112, 'w' => 90, 'h' => 4];
$coords['phone_cell'] = ['x' => 107, 'y' => 112, 'w' => 90, 'h' => 4];
$coords['phone_biz'] = ['x' => 13, 'y' => 124, 'w' => 90, 'h' => 4];
$coords['email'] = ['x' => 107, 'y' => 124, 'w' => 90, 'h' => 4];
$coords['contact_relationship'] = ['x' => 13, 'y' => 135, 'w' => 90, 'h' => 4];
$coords['phone_contact'] = ['x' => 107, 'y' => 135, 'w' => 90, 'h' => 4];

$coords['gp_name'] = ['x' => 13, 'y' => 147, 'w' => 90, 'h' => 4];
$coords['gp_surgery'] = ['x' => 107, 'y' => 147, 'w' => 90, 'h' => 4];

// more complex coords
$coords['dob'] = ['x' => 43.306, 'y' => 66.5, 'w' => 3.805, 'h' => 5];
$coords['title'] = [
    'Mr.' => ['x' => 12.4, 'y' => 58.4, 'w' => 3.4, 'h' => 4],
    'Mrs.' => ['x' => 28.2, 'y' => 58.4, 'w' => 3.4, 'h' => 4],
    'Miss.' => ['x' => 43.6, 'y' => 58.4, 'w' => 3.4, 'h' => 4],
    'Ms.' => ['x' => 59.2, 'y' => 58.4, 'w' => 3.4, 'h' => 4],
];
$coords['sex'] = [
    'Male' => ['x' => 12.4, 'y' => 75.2, 'w' => 3.4, 'h' => 4],
    'Female' => ['x' => 34.6, 'y' => 75.2, 'w' => 3.4, 'h' => 4],
    'Other' => ['x' => 58.6, 'y' => 75.2, 'w' => 3.4, 'h' => 4],
];

// data protection
$coords['hipaa_notice'] = ['x' => 192.4, 'y' => 208.3, 'w' => 3.4, 'h' => 4];
$coords['hipaa_voice'] = [
    'YES' => ['x' => 29.5, 'y' => 221.5, 'w' => 3.4, 'h' => 4],
    'NO' => ['x' => 45, 'y' => 221.5, 'w' => 3.4, 'h' => 4],
];
$coords['hipaa_allowsms'] = [
    'YES' => ['x' => 97.5, 'y' => 221.5, 'w' => 3.4, 'h' => 4],
    'NO' => ['x' => 113, 'y' => 221.5, 'w' => 3.4, 'h' => 4],
];
$coords['hipaa_allowemail'] = [
    'YES' => ['x' => 165.7, 'y' => 221.5, 'w' => 3.4, 'h' => 4],
    'NO' => ['x' => 181.4, 'y' => 221.5, 'w' => 3.4, 'h' => 4],
];

// barcode
$coords['barcode'] = [
    'fg' => ['x' => 40, 'y' => 274.5, 'w' => 0.35, 'h' => 3],
    'bg' => ['x' => 20.6, 'y' => 273, 'w' => 33.4, 'h' => 6.6],
];

//Get the data to place on the form
//
$patdata = sqlQuery("SELECT " .
    "p.title, p.fname, p.mname, p.lname, p.pubpid, p.DOB, p.sex, " .
    "p.street, p.city, p.state, p.postal_code, p.pid, " .
    "p.phone_home, p.phone_biz, p.phone_cell, p.contact_relationship, p.phone_contact ,p.email, " .
    "p.gp_name, p.gp_surgery, " .
    "p.hipaa_notice, p.hipaa_voice, p.hipaa_allowsms, p.hipaa_allowemail " .
    "FROM patient_data AS p " .
    "WHERE p.pid = ? LIMIT 1", array($pid));

// re-order the dates
//
$today = oeFormatShortDate($date = 'today');

// get barcode ready
$code     = $patdata['pubpid']; // what is wanted as the barcode
$bartype = $GLOBALS['barcode_label_type'] ; // Get barcode type

switch($bartype){
    case '1':
        $type     = 'std25';
        break;
    case '2':
        $type     = 'int25';
        break;
    case '3':
        $type     = 'ean8';
        break;
    case '4':
        $type     = 'ean13';
        break;
    case '5':
        $type     = 'upc';
        break;
    case '6':
        $type     = 'code11';
        break;
    case '7':
        $type     = 'code39';
        break;
    case '8':
        $type     = 'code93';
        break;
    case '9':
        $type     = 'code128';
        break;
    case '10':
        $type     = 'codabar';
        break;
    case '11':
        $type     = 'msi';
        break;
    case '12':
        $type     = 'datamatrix';
        break;
}


/**
 * Start rendering
 */
$pdf = new Fpdi();
$pdf->SetFont("Helvetica");

// build the background so we can overlay
$pdf->setSourceFile($GLOBALS['OE_SITE_DIR'] . "/registration_form.pdf");
$backId = $pdf->importPage(1);

$pdf->AddPage();
$pdf->useTemplate($backId);

addText($pdf, $coords['lname'], $patdata['lname']);
addText($pdf, $coords['fname'], $patdata['fname'] . " " . $patdata['mname']);

// address
addText($pdf, $coords['address1'], $patdata['street']);
if ($patdata['city'] && $patdata['state']) {
    $address = join(", ", [$patdata['city'], $patdata['state']]);
} else {
    $address = $patdata['city'] ? $patdata['city'] : $patdata['state'];
}
addText($pdf, $coords['address2'], $address);
addText($pdf, $coords['postal_code'], $patdata['postal_code']);

// contact details
addText($pdf, $coords['phone_home'], $patdata['phone_home']);
addText($pdf, $coords['phone_cell'], $patdata['phone_cell']);
addText($pdf, $coords['phone_biz'], $patdata['phone_biz']);
addText($pdf, $coords['email'], $patdata['email']);
addText($pdf, $coords['contact_relationship'], $patdata['contact_relationship']);
addText($pdf, $coords['phone_contact'], $patdata['phone_contact']);

addText($pdf, $coords['gp_name'], $patdata['gp_name']);
addText($pdf, $coords['gp_surgery'], $patdata['gp_surgery']);

// more complex fields
$dob = str_split(date('dmY', strtotime($patdata['DOB'])));
for ($i = 0; $i < 8; $i++) {
    $coord = $coords['dob'];
    $coord['x'] += $coord['w'] * $i;
    addText($pdf, $coord, $dob[$i]);
}

// checkmarks etc
$pdf->SetFont('ZapfDingbats');
$check = 3;
if (key_exists($patdata['title'], $coords['title'])) {
    addText($pdf, $coords['title'][$patdata['title']], $check);
}
if (key_exists($patdata['sex'], $coords['sex'])) {
    addText($pdf, $coords['sex'][$patdata['sex']], $check);
}

if ($patdata['hipaa_notice'] == "YES") {
    addText($pdf, $coords['hipaa_notice'], $check);
}
if (!empty($patdata['hipaa_voice'])) {
    addText($pdf, $coords['hipaa_voice'][$patdata['hipaa_voice']], $check);
}
if (!empty($patdata['hipaa_allowsms'])) {
    addText($pdf, $coords['hipaa_allowsms'][$patdata['hipaa_allowsms']], $check);
}
if (!empty($patdata['hipaa_allowemail'])) {
    addText($pdf, $coords['hipaa_allowemail'][$patdata['hipaa_allowemail']], $check);
}

$pdf->SetFont("Helvetica"); //reset

// -------------------------------------------------- //
//                      BARCODE
// -------------------------------------------------- //

$bg = $coords['barcode']['bg'];
$pdf->SetFillColor('255', '255', '255');
$pdf->Rect($bg['x'], $bg['y'], $bg['w'], $bg['h'], 'F');

$bcc = $coords['barcode']['fg'];
$fontSize = 12;
$margin = 0.5;
$data = Barcode::fpdf($pdf, "000", $bcc['x'], $bcc['y'], $angle, $type, array('code'=>$code), $bcc['w'], $bcc['h']);
$pdf->SetFont('Arial','B',$fontSize);
$pdf->SetTextColor(0, 0, 0);
$len = $pdf->GetStringWidth($data['hri']);
Barcode::rotate(-$len / 2, ($data['height'] / 2) + $fontSize / 4 + $margin, 0, $xt, $yt);
$pdf->Text($bcc['x'] + $xt, $bcc['y'] + $yt, $data['hri']);


$pdf->Output();
?>
