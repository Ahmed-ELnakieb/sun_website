<?php

/**
 * Sun Trading Company - Admin Login Page
 */

require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();

// Redirect if already logged in
if ($auth->isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_POST && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if ($auth->login($username, $password)) {
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<!--[if IE 8 ]><html class="ie" xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!-->
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US">
<!--<![endif]-->

<head>
    <!-- Basic Page Needs -->
    <meta charset="utf-8">
    <!--[if IE]><meta http-equiv='X-UA-Compatible' content='IE=edge,chrome=1'><![endif]-->
    <title>Admin Login - Sun Trading Company | Developed by Elnakieb</title>

    <meta name="author" content="Ahmed Elnakieb - ahmedelnakieb95@gmail.com">

    <!-- Mobile Specific Metas -->
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

    <!-- Theme Style -->
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/animation.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/bootstrap.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/styles.css">

    <!-- Font -->
    <link rel="stylesheet" href="assets/vendor/font/fonts.css">

    <!-- Icon -->
    <link rel="stylesheet" href="assets/vendor/icon/style.css">

    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/vendor/images/favicon.png">
    <link rel="apple-touch-icon-precomposed" href="assets/vendor/images/favicon.png">

    <style>
        :root {
            --Primary: #161326;
            --YellowGreen: #C0FAA0;
            --Orchid: #C388F7;
            --Khaki: #ECFF79;
            --LightSkyBlue: #AFC0FF;
            --White: #fff;
            --Black: #161326;
            --GrayDark: #6D6D6D;
            --Gray: #A4A4A9;
            --Gainsboro: #F8F8F8;
            --Salmon: #FD7972;
            --Green: #2BC155;
            --SunOrange: #E9A319;
            --SunGold: #A86523;
        }

        .sign-in-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--Primary) 0%, #24293E 100%);
            padding: 20px;
        }

        .sign-in-box {
            width: 100%;
            max-width: 1200px;
            display: flex;
            align-items: stretch;
            justify-content: center;
            background-color: var(--White);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .sign-in-box .left {
            width: 50%;
            padding: 72px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sign-in-box .left .content {
            max-width: 485px;
            width: 100%;
        }

        .sign-in-box .left .content .heading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 8px;
            font-size: 32px;
            font-weight: 700;
            color: var(--Primary);
        }

        .sign-in-box .left .content .heading .sun-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--SunGold) 0%, var(--SunOrange) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .sign-in-box .left .content .sub {
            text-align: center;
            margin-bottom: 32px;
            color: var(--GrayDark);
            font-size: 16px;
        }

        .sign-in-box .left .sign-in-inner {
            padding: 40px;
            border-radius: 16px;
            background-color: var(--Gainsboro);
            display: flex;
            gap: 32px;
            flex-direction: column;
        }

        .sign-in-box .left .sign-in-inner h4 {
            font-size: 24px;
            font-weight: 600;
            color: var(--Primary);
            margin: 0 0 8px 0;
            text-align: center;
        }

        .form-login {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .form-login fieldset {
            border: none;
            padding: 0;
            margin: 0;
        }

        .form-login fieldset .field-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--Primary);
        }

        .form-login fieldset input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            background-color: var(--White);
            transition: all 0.3s ease;
            outline: none;
        }

        .form-login fieldset input:focus {
            border-color: var(--SunOrange);
            box-shadow: 0 0 0 3px rgba(233, 163, 25, 0.1);
        }

        .form-login fieldset input::placeholder {
            color: var(--Gray);
        }

        .tf-button {
            width: 100%;
            padding: 14px 24px;
            background: linear-gradient(135deg, var(--SunGold) 0%, var(--SunOrange) 100%);
            color: var(--White);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .tf-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233, 163, 25, 0.3);
        }

        .tf-button:active {
            transform: translateY(0);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .alert-danger {
            background-color: #fee;
            color: #c53030;
            border: 1px solid #fed7d7;
        }

        .alert-success {
            background-color: #f0fff4;
            color: #2d7d32;
            border: 1px solid #c6f6d5;
        }

        .sign-in-box .right {
            width: 50%;
            position: relative;
            background: linear-gradient(135deg, var(--Primary) 0%, #24293E 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sign-in-box .right .text {
            text-align: center;
            color: var(--White);
            padding: 48px;
        }

        .sign-in-box .right .text .logo-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--SunGold) 0%, var(--SunOrange) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
            font-size: 48px;
            color: white;
            box-shadow: 0 10px 30px rgba(233, 163, 25, 0.3);
        }

        .sign-in-box .right .text h3 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .sign-in-box .right .text .description {
            font-size: 16px;
            line-height: 1.6;
            opacity: 0.9;
        }

        .login-footer {
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid #e1e5e9;
            font-size: 14px;
            color: var(--GrayDark);
        }

        .login-footer code {
            background-color: var(--Primary);
            color: var(--White);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sign-in-box {
                flex-direction: column;
                margin: 10px;
            }

            .sign-in-box .left,
            .sign-in-box .right {
                width: 100%;
            }

            .sign-in-box .left {
                padding: 40px 20px;
            }

            .sign-in-box .left .sign-in-inner {
                padding: 24px;
            }

            .sign-in-box .right {
                min-height: 200px;
            }

            .sign-in-box .right .text {
                padding: 24px;
            }

            .sign-in-box .right .text .logo-icon {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }

            .sign-in-box .right .text h3 {
                font-size: 24px;
            }

            .sign-in-box .left .content .heading {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>
    <!-- #wrapper -->
    <div id="wrapper">
        <!-- #page -->
        <div id="page" class="">
            <div class="sign-in-wrap">
                <div class="sign-in-box">
                    <div class="left">
                        <div class="content">
                            <h3 class="heading">
                                <div class="sun-icon">
                                    <i class="fas fa-sun"></i>
                                </div>
                                Sun Trading
                            </h3>
                            <div class="sub">Welcome back to our admin dashboard</div>
                            <div class="sign-in-inner">
                                <h4>Admin Sign In</h4>

                                <?php if ($error): ?>
                                    <div class="alert alert-danger" role="alert">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?php echo htmlspecialchars($error); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($success): ?>
                                    <div class="alert alert-success" role="alert">
                                        <i class="fas fa-check-circle"></i>
                                        <?php echo htmlspecialchars($success); ?>
                                    </div>
                                <?php endif; ?>

                                <form class="form-login" method="POST" action="">
                                    <fieldset class="email">
                                        <div class="field-label">Username or Email</div>
                                        <input type="text"
                                            placeholder="Enter your username or email"
                                            name="username"
                                            value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                            required>
                                    </fieldset>
                                    <fieldset class="password">
                                        <div class="field-label">Password</div>
                                        <input type="password"
                                            placeholder="Enter your password"
                                            name="password"
                                            required>
                                    </fieldset>
                                    <button type="submit" name="login" class="tf-button">
                                        <i class="fas fa-sign-in-alt"></i>
                                        Sign In
                                    </button>
                                </form>

                                <div class="login-footer">
                                    <strong>Default Login:</strong><br>
                                    Username: <code>admin</code> | Password: <code>admin123</code>
                                    <hr style="margin: 16px 0; border-color: #e1e5e9;">
                                    <div style="font-size: 12px; color: var(--Gray);">
                                        Developed by <a href="mailto:ahmedelnakieb95@gmail.com" style="color: var(--SunOrange); text-decoration: none; font-weight: 600;">Elnakieb</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="right">
                        <div class="text">
                            <div class="logo-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3>Manage & Monitor Your Business</h3>
                            <div class="description">
                                Access your comprehensive dashboard to manage products,<br>
                                monitor analytics, and control your digital presence<br>
                                with our powerful admin tools.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /#page -->
    </div>
    <!-- /#wrapper -->

    <!-- Javascript -->
    <script src="assets/vendor/js/main.js"></script>
</body>

</html>