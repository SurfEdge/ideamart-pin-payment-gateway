<?php

###### CONFIG ###########

# For more information check this. 
# http://docs.ideabiz.lk/Getting_Started/Token_Manegment
# http://docs.ideabiz.lk/APIs/PIN-Payment

$username = "ideabiz_user";
$password = "ideabiz_pass";
$auth_token = "AUTH_TOKEN";
$description = "PAYMENT DESCRIPTION";
$tax = 0.1735; # 17.35%

#########################

session_start();

if (isset($_POST['tamount']) || isset($_POST['tid']) || isset($_POST['pid']) || isset($_POST['rurl'])) {
    $_SESSION['tamount'] = $_POST['tamount'];
    $_SESSION['tid'] = $_POST['tid'];
    $_SESSION['pid'] = $_POST['pid'];
    $_SESSION['rurl'] = $_POST['rurl'];
} elseif (!isset($_SESSION['tamount']) || !isset($_SESSION['tid']) || !isset($_SESSION['pid'])) {
    die("Unauthorized Access!");
}

$payment = true;
$amount = $_SESSION['tamount'];
$tid = $_SESSION['tid'];
$pid = $_SESSION['pid'];
$rurl = $_SESSION['rurl'];
$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => "https://ideabiz.lk/apicall/token?grant_type=password&username=$username&password=$password&scope=PRODUCTION",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_HTTPHEADER => array(
        "Authorization: Basic $auth_token",
        "Cache-Control: no-cache"),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
    $token = json_decode($response, true);
    $token = $token['access_token'];
}

if (isset($_POST['submit'])) {

    $ptn = "/^0/";
    $mobile = preg_replace($ptn, '+94', $_POST['number']);
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://ideabiz.lk/apicall/pin/payment/v1/charge",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "{\n  \"msisdn\": \"tel:$mobile\",\n  \"description\": \"$description\",\n  \"taxable\": false,\n  \"callbackURL\": \"$rurl\",\n  \"txnRef\": \"$tid-$pid\",\n  \"amount\": $amount\n}",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $token",
            "Cache-Control: no-cache",
            "Content-Type: application/json",
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $res_otp = json_decode($response, true);
        if ($res_otp['statusCode'] == 'ERROR') {
            $payment_error = true;
        }

        if ($res_otp['statusCode'] == 'SUCCESS') {
            $otp_sent = true;
            $payment = false;
            $server_ref = $res_otp['data']['serverRef'];
        }
    }
}

if (isset($_POST['submit_otp'])) {
    $payment = false;

    $otp = $_POST['otp'];
    $server_ref = $_POST['server_ref'];

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://ideabiz.lk/apicall/pin/payment/v1/submitPin",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "{\n  \"pin\": \"$otp\",\n  \"serverRef\": \"$server_ref\"\n}",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $token",
            "Cache-Control: no-cache",
            "Content-Type: application/json",
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $otp_sent = true;

        $res_otp = json_decode($response, true);

        if ($res_otp['statusCode'] == 'ERROR') {
            $otp_error = true;
            $otp_message = $res_otp['message'];
        }
        if ($res_otp['statusCode'] == 'SUCCESS') {
            $otp_sent = false;
            $otp_valid = true;
            if ($res_otp['data']['status'] == "FAILED") {
                $otp_success = false;
                $otp_message = $res_otp['message'];
            }
            if ($res_otp['data']['status'] == "SUCCESS") {
                $otp_success = true;
            }

        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="SurfEdge">

    <title>Dialog Mobile Payments</title>

    <!-- Bootstrap core CSS -->
    <link href="assets/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="assets/form-validation.css" rel="stylesheet">
  </head>

  <body class="bg-light">

    <div class="container">
      <div class="py-5 text-center">
        <img class="d-block mx-auto mb-4" src="assets/surfedge.png" alt="" width="250">
        <h2>Dialog Mobile Payment</h2>
        <h5 class="mb-3">Payment of Rs. <?php echo $amount; ?></h5>
        <h6>Excluding NBT+VAT (<?php echo $tax*100; ?>%) = Rs. <?php echo round($amount * $tax, 2); ?> </h6>
        <p class="lead"></p>
      </div>

      <div class="row">
        <div class="col-md-3"></div>


        <?php if ($payment) {?>
        <div class="col-md-6">
          <h4 class="mb-3">Dialog Mobile Number</h4>
          <form class="needs-validation" novalidate="" method="POST">
            <div class="row">
              <div class="col-md-12 mb-3">
                <input hidden="" name="submit">
                <input placeholder="07XXXXXXXX" type="text" class="form-control" id="number" name="number" placeholder="" value="" required="">
                <div class="invalid-feedback">
                  Valid Mobile number is required.
                </div>
              </div>
            </div>
            <?php if ($payment_error) {?>

            <div class="alert alert-danger" role="alert">
              Please check your mobile number! Ex: 0771234567
            </div>

            <?php }?>
            <hr class="mb-4">
            <button class="btn btn-primary btn-lg btn-block" type="submit">Pay Rs. <?php echo $amount + round($amount * 0.1735, 2); ?></button>



          </form>
        </div>
        <?php }?>

        <?php if ($otp_sent) {?>
        <div class="col-md-6">
          <h4 class="mb-3">Please enter the OTP code sent to your mobile</h4>
          <form class="needs-validation" novalidate="" method="POST">
            <div class="row">
              <div class="col-md-12 mb-3">
                <input hidden="" name="submit_otp">
                <input hidden="" name="server_ref" value="<?php echo $server_ref; ?>">
                <input placeholder="OTP PIN" type="text" class="form-control" id="otp" name="otp" placeholder="" value="" required="">
                <div class="invalid-feedback">
                  Valid OTP is required. Check your SMS
                </div>
              </div>
            </div>

            <?php if ($otp_error) {?>

            <div class="alert alert-danger" role="alert">
              <?php echo $otp_message; ?>
            </div>

            <?php }?>

            <hr class="mb-4">
            <button class="btn btn-success btn-lg btn-block" type="submit">Confirm Payment</button>
          </form>
        </div>
        <?php }?>

        <?php
          if ($otp_valid) {
              echo '<form action="' . $rurl . '" id="ideamart" method="POST">';

              echo "<input type='hidden' value='$tid' name='tid'>";
              echo "<input type='hidden' value='$pid' name='pid'>";
              echo "<input type='hidden' value='$tamount' name='tamount'>";
              echo "<input type='hidden' value='$server_ref' name='server_ref'>";
              echo "<input type='hidden' value='$otp_success' name='status'>";

              echo '</form>';
              echo "<script type=\"text/javascript\">
                          function loadFrame() {
                            document.getElementById('ideamart').submit();
                          };
                          window.onload = setTimeout(loadFrame, 2500);
                        </script>
                    ";
          }

          ?>


        <?php if ($otp_valid && $otp_success) {?>
        <div class="col-md-6">
          <div class="alert alert-success" role="alert">
            <h4 class="alert-heading">Payment Successful!</h4>
            <p>You will be automatically redirected back to the Merchant site</p>
            <hr>
          </div>
        </div>
        <?php }?>

        <?php if ($otp_valid && !$otp_success) {?>
        <div class="col-md-6">
          <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading">Payment Failed!</h4>
            <p><strong><?php echo $otp_message; ?></strong></p>
            <hr>
          </div>
        </div>
        <?php }?>


      </div>

      <footer class="my-5 pt-5 text-muted text-center text-small">
        <p class="mb-1">© 2018 <a href="http://surfedge.lk">SurfEdge</a></p>
      </footer>
    </div>

    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="assets/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
    <script>window.jQuery || document.write('<script src="assets/js/vendor/jquery-slim.min.js"><\/script>')</script>
    <script src="assets/popper.min.js"></script>
    <script src="assets/bootstrap.min.js"></script>
    <script src="assets/holder.min.js"></script>
    <script>
      (function() {
        'use strict';
        window.addEventListener('load', function() {
          var forms = document.getElementsByClassName('needs-validation');
          var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
              if (form.checkValidity() === false) {
                event.preventDefault();
                event.stopPropagation();
              }
              form.classList.add('was-validated');
            }, false);
          });
        }, false);
      })();
    </script>

</body></html>