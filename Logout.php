<?php
/*! Logout.php | Invalidate and remove user session. */

framework\Session::invalidate() &&
  setcookie('sid', '', time() - 3600);

$req = $this->reqeust;
$res = $this->response;

$target = $req->param('returnUrl');
if ( !$target ) {
  $target = $req->client('referer');
}

if ( !$target ) {
  $target = '/';
}

$res->redirect($target);
