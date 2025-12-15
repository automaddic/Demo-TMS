<?php
session_start();
$fromRegister = $_SESSION['from_register'] ?? false;
unset($_SESSION['from_register']);

?>
<!doctype html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>TMS Login</title>
	<link rel="stylesheet" href="styles/login.css">
</head>

<body>
	<section class="Log-in">
		<div>
			<div>
				<div class="img-cont">
					<img src="images/sope-200.png" alt="Logo">
				</div>
				<div class="title-cont">
					<p>Login to Team Management Software Demo</p>
				</div>
				<hr id="split-1">
			</div>

			<form id="login-form" method="POST" action="router-api.php?path=login.php&action=local">
				<div id="manual-login">
					<?php if (!$fromRegister): ?>
						<?php if (isset($_SESSION['error'])): ?>
							<p class="error"><?php echo $_SESSION['error'];
							unset($_SESSION['error']); ?></p>
						<?php endif; ?>
					<?php endif; ?>
					<div>
						<p style="color: white;">Login to the site by clicking the button below (No username or password required).</br></br>You can also create an account below (don't use real information) and log in with those credentials to see a demonstration of security measures available on the site.</br></br>Please visit <a href="https://github.com/automaddic/Demo-TMS/blob/main/README.md">GitHub</a> for important information about the operation of this demonstration as well as the code source.</p>
						<label>Email / Username</label>
						<input id="email-in" name="identifier">
						<label>Password</label>
						<input id="pass-in" type="password" name="password">
					</div>

					<button type="submit" id="LoginPOST">Login</button>
					<a href="register.php">Don't have an account?</a>
				</div>
			</form>

			<div id="split-2">
				<hr id="split-2-1">
				<hr id="split-2-2">
			</div>
		</div>
	</section>
</body>

</html>
