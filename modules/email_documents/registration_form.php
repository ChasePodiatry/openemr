#! /usr/bin/php

<?php
// hack add for command line version
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTP_HOST'] = 'default';
$backpic = "";

$ignoreAuth = true;
require_once(__DIR__ . "/../../interface/globals.php");
require_once("$srcdir/fpdf/fpdf.php");
require_once("$srcdir/fpdi/autoload.php");
require_once("$srcdir/documents.php");
require_once("$srcdir/patient.inc");
require_once("$srcdir/classes/postmaster.php");

use setasign\Fpdi\Fpdi;

class File
{
    public $data; // file handle to a php://temp resource
    public $type; // the mime type of the file
    public $ppid; // the public patient ID
    public $pid; // patient id

    function __construct($filedata, $type)
    {
        $this->data = $filedata;
        $this->type = $type;
    }

    function Delete()
    {
        fclose($this->data);
    }

    function ReadBarcode()
    {
        $tmpfile = stream_get_meta_data(tmpfile())['uri'];

        $im = new Imagick();
        $im->setResolution(300, 300);
        $im->readImageBlob($this->data);
        $im->setImageFormat("jpg");
        $im->writeImage($tmpfile);
        $im->clear();
        $im->destroy();

        exec("zbarimg --raw -q " . $tmpfile, $result, $status);
        if ($status != 0) {
            return false;
        }

        // pid may have spaces in, will produce an array result, we should concat them with " "
        $this->ppid = implode(" ", $result);
        $patients = getPatientId($this->ppid, "id, pubpid", "pubpid");
        if (sizeof($patients) == 0) {
            return false;
        }
        if (sizeof($patients) > 1) {
            // we have multiple, need to check for an exact match
            foreach($patients as $patient) {
                if ($patient['pubpid'] === $this->ppid) {
                    $this->pid = $patient['id'];
                }
            }
        } else {
            $this->pid = $patients[0]['id'];
        }

        return $this->pid;
    }

    function Save($name, $cat_id)
    {
        $tmpFile = tmpfile();
        $size = fwrite($tmpFile, $this->data);
        $ok = addNewDocument($name, $this->type, stream_get_meta_data($tmpFile)['uri'], "", $size, 0, $this->pid, $cat_id);
        fclose($tmpFile);
        if ($ok === false) {
            return false;
        }
        return true;
    }

    function MIMEEncode()
    {

    }
}

// fetch data from stdin
$email = file_get_contents("php://stdin");

$mailparse = mailparse_msg_create();
mailparse_msg_parse($mailparse, $email);
$structure = mailparse_msg_get_structure($mailparse);

$success = array();
$failure = array();

function decode_attachment($mime_data, $mime_attachment, $files)
{
    switch ($mime_data['transfer-encoding']) {
        case 'base64':
            $file = base64_decode($mime_attachment);
            break;
        default:
            error_log("unable to decode attachment");
            return null;
    }

    switch ($mime_data['content-type']) {
        case "application/pdf":
            // write to a temp file so we can read it in later
            $tmpFile = fopen("php://temp", "r+");
            fwrite($tmpFile, $file);

            $pdfRoot = new Fpdi();
            $pageCount = $pdfRoot->setSourceFile($tmpFile);


            for ($i = 1; $i <= $pageCount; $i++) {
                $new_pdf = new Fpdi();
                $new_pdf->AddPage();
                $new_pdf->setSourceFile($tmpFile);
                $new_pdf->useTemplate($new_pdf->importPage($i));

                try {
                    $data = $new_pdf->Output("page" . $i, "S");
                    $files[$mime_data['content-name'] . $i] = new File($data, "application/pdf");
                } catch (Exception $e) {
                    echo 'Caught exception: ' . $e->getMessage() . "\n";
                }
            }

            break;

        default:
            echo 'unknown content-type';
    }

    return $files;
}

$files = array();

foreach ($structure as $structurepart) {
    $part = mailparse_msg_get_part($mailparse, $structurepart);
    $partdata = mailparse_msg_get_part_data($part);

    if ($partdata['content-disposition'] === 'attachment' || $partdata['content-disposition'] === "inline") {
//        foreach ($partdata as $key=>$value) {
//            echo $key . ": " . $value . "\n";
//        }

        $startingposition = $partdata['starting-pos-body'];
        $length = $partdata['ending-pos-body'] - $partdata['starting-pos-body'];

        $mime_attachment = substr($email, $startingposition, $length);

        $files = decode_attachment($partdata, $mime_attachment, $files);
    }
}

// do the final file processing
$category_id = document_category_to_id($GLOBALS['patient_registration_form_category_name']);
foreach ($files as $file) {
    $ok = $file->ReadBarcode();
    if ($ok === false) {
        $failure[] = $file;
        continue;
    }

    $ok = $file->Save($GLOBALS['patient_registration_form_file_name'], $category_id);
    if ($ok) {
        $success[] = $file;
    } else {
        $failure[] = $file;
    }
}

echo "Successes: " . sizeof($success) . "\t Failures: " . sizeof($failure) . "\n";

// produce summary report
$successCount = sizeof($success);
$failureCount = sizeof($failure);

$subject = "[records][registration form] ";
$subject .= $failureCount ? "Error: $successCount succeeded, $failureCount failed" : "Success: imported $successCount forms";

$body = "This is an automated message from the OpenEMR registration form importer.\n" .
    "A total of " . ($successCount + $failureCount) . " were processed. $successCount succeeded, $failureCount failed.\n\n";

if ($successCount > 0) {
    $body .= "Successful forms were located for:\n";
    foreach ($success as $file) {
        $body .= $file->ppid . "\n";
    }
    $body .= "\n";
}

if ($failureCount > 0) {
    $noPid = 0;

    $body .= "The following errors were encountered:\n";
    foreach ($failure as $file) {
        if ($file->pid) {
            $body .= $file->ppid . "\t error saving\n";
        } else {
            if ($file->ppid) {
                $body .= $file->ppid . "\t not found\n";
            } else {
                $noPid++;
            }
        }
    }
    if ($noPid > 0) {
        $body .= "No PID could be found for $noPid files\n";
    }
    $body .= "\n";
}

$body .= "Please verify these are the results you are expecting. If they are not, please inform the administrator.\n";

echo $body;

$mail = new MyMailer();
try {
    $msg = mailparse_msg_get_part($structure, $structure[0]);
    $msg_data = mailparse_msg_get_part_data($msg);
    $mail->SetFrom("openemr@".gethostname());
    $mail->AddAddress($GLOBALS["practice_return_email_path"]);
    $mail->Subject = $subject;
    $mail->Body = $body;

    foreach ($failure as $file) {
        /* @var File $file */
        $tmpFile = tmpfile();
        fwrite($tmpFile, $file->data);

        $mail->AddAttachment(stream_get_meta_data($tmpFile)['uri'],
            $file->pid ? $file->pid : $GLOBALS['patient_registration_form_file_name'],
            "base64",
            $file->type
        );
    }

    $mail->Send();
} catch (phpmailerException $e) {
    var_dump($e);
}

exit(0);
