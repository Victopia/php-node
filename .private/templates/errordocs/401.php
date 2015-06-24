<!doctype html>

<html lang="en">
	<head>
		<meta charset="utf-8"/>
		<title>401 Unauthorized | <?php echo framework\System::getHostname() ?></title>
	</head>

	<body>
		<h1>401 Unauthorized</h1>

		<p>Requested resource <?php echo $this->request()->uri('path') ?> is not authorized, you may need to signin with appropriate identity before access.</p>

		<hr />

		<sub>Gateway repsonse.</sub>
	</body>
</html>
