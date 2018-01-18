<?php

include __DIR__ . '/vendor/autoload.php';

// --- Amazon Simple Email Service credentals and mail constants (you need to fill these in!) 
// --- See https://docs.aws.amazon.com/ses/latest/DeveloperGuide/send-using-smtp-php.html
const FROM_ADDRESS = '';
const FROM_NAME = '';

const TO_ADDRESS = '';
const TO_NAME = '';

const USER_NAME = '';
const PASSWORD = '';

const HOST = '';
const PORT = 0;

// --- Shell command to run apt-check, which on Ubuntu will execute a check for updates and return the number of
// --- packages available for update. If you are using a different flavor of Linux then you may have to modify this
// --- commmand.
const APT_CHECK_COMMAND = '/usr/lib/update-notifier/apt-check --human-readable';

// --- Indicate where the results log should be stored. NOTE: the process running cron will need to have premissions
// --- to write to this directory.
const RESULTS_FILE = '/usr/local/sbin/any-updates-available/cron-results.log';

// --- If this debug switch is on, always send a daily email indicating the results of running the script. If this
// --- switch is off, only send emails when there are packages waiting to be installed.
const DEBUG_UPDATES_SCRIPT = TRUE;

// -----------------------------------------------------------------------------------------------------------------

// --- Check if the apt-check command indicates there are packages that need to be updated
$check_updates_available_output = '';
$return_value = '';
$matches = '';
exec(APT_CHECK_COMMAND, $check_updates_available_output, $return_value);

// --- First make sure the exec() call to apt-check succeeded. If it failed email me about that!
if ($return_value !== 0) {
    mail_me('any-updates-available script: script failed to exec() apt-check command');
    exit;
}
// --- Or else we got a clean exec() call to apt-check. Now check if we get a regex pattern match for packages needing
// --- to be updated. If there is a pattern match or we're in DEBUG mode then send me an email about it. 
else {
    $check_updates_available_output = implode("\n", $check_updates_available_output);
    if (preg_match("/[1-9][0-9]* packages can be updated./", $check_updates_available_output, $matches)
        || (DEBUG_UPDATES_SCRIPT)) {
        mail_me("any-updates-available script: \n" . $check_updates_available_output);
    }

    // Finally record the results in a file in the specified directory which cron can write to. NOTE: the process
    // executing cron will need write access to this directory. To make cron run as root, first do "sudo su"
    // then "crontab -e" to edit the cron file. If you do this as root then this script will run as root. Of course,
    // you will need sudo privileges for this to work.
    $today = date("F j, Y, g:i a"); 
    if (empty(trim($check_updates_available_output))) {
        $results_string = $today . ' : ' . 'No updates available.' . "\r\n"; 
    }
    else {
        $results_string = $today . ' : ' . $check_updates_available_output . "\r\n"; 
    }
    
    $results_string .= "-------------------------------------------------------------\r\n";
    file_put_contents(RESULTS_FILE, $results_string, FILE_APPEND);
}

// ---
// --- Function to email me a message. Amazon AWS template code here was adapted as a template:
// --- https://docs.aws.amazon.com/ses/latest/DeveloperGuide/send-using-smtp-php.html.
// --- Note that the From and To addresses must be verified with AWS, and you will need AWS SES credentails as
// --- documented in the above link.
// ---
function mail_me($message_string)
{
    $mail = new PHPMailer; // Instantiate a new PHPMailer

    // Tell PHPMailer to use SMTP
    $mail->isSMTP();

    // Replace sender@example.com with your "From" address.
    // This address must be verified with Amazon SES.
    $mail->setFrom(FROM_ADDRESS, FROM_NAME);

    // Replace recipient@example.com with a "To" address. If your account
    // is still in the sandbox, this address must be verified.
    // Also note that you can include several addAddress() lines to send
    // email to multiple recipients.
    $mail->addAddress(TO_ADDRESS, TO_NAME);

    // Replace smtp_username with your Amazon SES SMTP user name.
    $mail->Username = USER_NAME;

    // Replace smtp_password with your Amazon SES SMTP password.
    $mail->Password = PASSWORD;

    // Specify a configuration set. If you do not want to use a configuration
    // set, comment or remove the next line.
    //$mail->addCustomHeader('X-SES-CONFIGURATION-SET', 'ConfigSet');

    // Set the HOST constant to the Amazon SES SMTP endpoint in the appropriate region for your EC2 instance
    $mail->Host = HOST;

    // The subject line of the email
    $mail->Subject = 'Message from any-updates-available script';

    // The HTML-formatted body of the email
    $mail->Body = '<h1>Message from any-updates-available script</h1>'
        . '<p>' . $message_string . '</p>';

    // Tells PHPMailer to use SMTP authentication
    $mail->SMTPAuth = true;

    // Enable TLS encryption over the port specified by Amazon SES
    $mail->SMTPSecure = 'tls';
    $mail->Port = PORT;

    // Tells PHPMailer to send HTML-formatted email
    $mail->isHTML(true);

    // The alternative email body; this is only displayed when a recipient
    // opens the email in a non-HTML email client. The \r\n represents a
    // line break.
    $mail->AltBody = "Message from any-updates-available script\r\n" . $message_string . "\r\n";

    // --- Send the email message
    $mail->send();        
}
