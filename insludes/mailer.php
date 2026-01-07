<?php
function send_password_reset_email($email, $link) {
    $to = $email;
    $subject = "तपाईंको पासवर्ड रिसेट अनुरोध";
    
    $message = "
    <html>
    <head>
        <title>पासवर्ड रिसेट</title>
    </head>
    <body>
        <h2>पासवर्ड रिसेट गर्नुहोस्</h2>
        <p>यो लिंक १ घण्टाको लागि मात्र वैध छ:</p>
        <a href='$link' style='background: #0066CC; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>पासवर्ड रिसेट गर्नुहोस्</a>
        <p>यदि तपाईंले यो अनुरोध गर्नुभएको छैन भने, यसलाई बेवास्ता गर्नुहोस्।</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: no-reply@tradingsite.com" . "\r\n";
    
    // वास्तविक प्रयोगमा PHPMailer वा अन्य लाइब्रेरी प्रयोग गर्नुहोस्
    return mail($to, $subject, $message, $headers);
}
?>