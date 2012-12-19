<!doctype html>

<html lang="en">
	<head>
		<meta charset="utf-8"/>
		<title>500 Internal Server Error | Come2list.com</title>
	</head>

	<body>
		<h1>500 Internal Server Error</h1>
		
		<p>We have an error when processing the request, and our administrator has been notified.</p>
		
		<p>Please try again later.</p>
		
		<hr />
		
		<sub>Gateway repsonse.</sub>
	</body>
</html>
<?php

require_once("$_SERVER[DOCUMENT_ROOT]/scripts/Initialize.php");

@\core\Log::write('500 Server Error: Serialized $_SERVER as, ' . serialize($_SERVER));

?>