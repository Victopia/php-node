<?php /*! logout.php | Invalidate and remove user session. */

framework\Session::invalidate();

$req = $this->request();
$res = $this->response();

$target = $req->param('returnUrl');
if ( !$target ) {
  $target = $req->client('referer');
}

if ( !$target ) {
  $target = '/';
}

$res->cookie('__sid', null, time() - 3600)->redirect($target);
