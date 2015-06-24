<!doctype html>

<html lang="en">
	<head>
		<meta charset="utf-8"/>
		<title>404 Not Found | <?php echo framework\System::getHostname() ?></title>
	</head>

	<body>
		<h1>404 Not Found</h1>

		<p>Requested resource <?php echo $this->request()->uri('path') ?> cannot be located on the server.</p>

		<hr />

		<sub>Gateway repsonse.</sub>
	</body>
</html>
