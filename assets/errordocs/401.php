<!doctype html>

<html lang="en">
	<head>
		<meta charset="utf-8"/>
		<title>401 Unauthorized | <?php echo $_SERVER['HTTP_HOST']; ?></title>
	</head>

	<body>
		<h1>401 Unauthorized</h1>

		<p>Requested resource <?php echo $_SERVER['REQUEST_URI']; ?> is not authorized, you may need to signin with appropriate identity before access.</p>

		<hr />

		<sub>Gateway repsonse.</sub>
	</body>
</html>