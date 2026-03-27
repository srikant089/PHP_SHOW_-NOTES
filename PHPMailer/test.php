<?php
require 'class.phpmailer.php';

$mail = new PHPMailer(true);

  $from = 'example.smtp@gmail.com'; 
  $from_name = 'example';
  $to = 'abc@yopmail.com';


$mail->isSMTP(); 
$mail->SMTPDebug = false;                            // Set mailer to use SMTP
$mail->SMTPAuth = true;                     // Enable SMTP authentication
$mail->SMTPKeepAlive = true;   
$mail->SMTPSecure = 'tls';                  // Enable TLS encryption, `ssl` also accepted
$mail->Port = 587;                          // TCP port to connect to
$mail->Mailer = 'smtp'; // don't change the quotes!
$mail->Host = 'smtp.gmail.com';             // Specify main and backup SMTP servers
$mail->Username = 'example.smtp@gmail.com';  
$mail->Password = 'xxxxxxxxx';

$mail->setFrom( $from, $from_name);
$mail->addAddress($to);   // Add a recipient
$mail->addReplyTo($from, $from_name);
$mail->isHTML(true);  // Set email format to HTML

$bodyContent = '<h1>How to Send Email using PHP in Localhost by CodexWorld</h1>';
$bodyContent .= '<p>This is the HTML email sent from localhost using PHP script by <b>CodexWorld</b></p>';

$mail->Subject = 'Email from Localhost by CodexWorld';
$mail->Body    = $bodyContent;

if(!$mail->send()) {
    echo 'Message could not be sent.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
} else {
    echo 'Message has been sent';
}
    


?>
