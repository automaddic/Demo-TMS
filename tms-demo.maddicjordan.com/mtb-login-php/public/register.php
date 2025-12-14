<?php
session_start();


// Optional: Only show message after POST
$fromRegister = $_SESSION['from_register'] ?? false;
unset($_SESSION['from_register']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Sope Creek MTB</title>
    <link rel="stylesheet" href="styles/register.css">
</head>

<body>
    <section class="register">
        <div>
            <div class="img-cont">
                <img src="images/sope-200.png" alt="Logo">
            </div>

            <div class="title-cont">
                <p>Register for Sope Creek MTB</p>
            </div>

            <hr class="split-1">


            <?php if (isset($_SESSION['error'])): ?>
                <p class='error'><?= htmlspecialchars($_SESSION['error']) ?></p>
                <?php unset($_SESSION['error']); ?>
            <?php elseif (isset($_SESSION['success'])): ?>
                <p class='success'><?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?></p>
            <?php endif; ?>


            <form id="register-form" method="POST" action="router-api.php?path=register.php">
                <div>
                    <label>Username</label>
                    <input id="username" name="username" type="text" required>

                    <label>Email</label>
                    <input id="email" name="email" type="email" required>

                    <hr class="split-1">

                    <label>Password</label>
                    <input id="password" name="password" type="password" required>

                    <label>Confirm Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" required>
                </div>
                <button type="submit">Register</button>
            </form>

            <a href="login.php">Already have an account?</a>
        </div>
    </section>

    <?php if ($fromRegister && isset($_SESSION['just_registered'])): ?>
        <script>
            setTimeout(() => {
                window.location.href = "login.php";
            }, 3000);
        </script>
        <?php unset($_SESSION['just_registered']); ?>
    <?php endif; ?>
</body>

</html>