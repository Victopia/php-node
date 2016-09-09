<?php
/*! Login.php | Simple user login. */

use framework\Session;
use framework\exceptions\FrameworkException;

$req = $this->request();
$res = $this->response();

if ( @$req->user->identity() ) {
  $res->redirect(
    $req->param('returnUrl') ?
      $req->param('returnUrl') :
      $req->client('referer')
    );
  die;
}

if ( $req->post('username') && $req->post('password') ) {
  try {
    $session = Session::validate($req->post('username'), $req->post('password'), $req->fingerprint());
  }
  catch (FrameworkException $e) {
    switch ( $e->getCode() ) {
      case Session::ERR_MISMATCH:
        $ret = 'Username and password mismatch';
        break;

      case Session::ERR_EXISTS:
        // $ret = 'You have not logged out from last session, login again to override.';
        $session = $e->getContext();
        Session::ensure($session['sid'], null, $req->fingerprint());
        break;

      case Session::ERR_EXPIRED:
        $ret = 'Your session has expired, login again to restore it.';
        break;

      default:
        throw $e;
    }
  }

  if ( isset($session) ) {
    $res
      ->cookie('__sid', $session['sid'], FRAMEWORK_COOKIE_EXPIRE_TIME, '/')
      ->redirect($req->param('returnUrl'));

    exit;
  }
}

unset($req, $res);
?>
<!doctype html>

<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <title>Login | <?php echo $this->__('system.title') ?></title>

    <!-- jQuery -->
    <script src="//code.jquery.com/jquery.min.js"></script>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/latest/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/latest/css/bootstrap-theme.min.css"/>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/latest/js/bootstrap.min.js"></script>

    <script>$(function() {$('#txtUID').focus()});</script>
  </head>

  <body>
    <div class="container">
      <div class="row">
        <div class="col-sm-offset-3 col-sm-6">
          <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="post" class="form-horizontal">
            <div class="form-group">
              <div class="col-sm-12">
                <h1>Login</h1>
              </div>
            </div>

            <?php if (isset($ret)): ?>
            <div class="alert alert-danger"><?php echo $ret ?></div>
            <?php endif ?>

            <div class="form-group">
              <label for="txtUID" class="col-sm-3 control-label">Username</label>
              <div class="col-sm-9">
                <input type="text" name="username" id="txtUID" class="form-control"/>
              </div>
            </div>

            <div class="form-group">
              <label for="txtPWD" class="col-sm-3 control-label">Password</label>
              <div class="col-sm-9">
                <input type="password" name="password" id="txtPWD" class="form-control"/>
              </div>
            </div>

            <div class="form-group">
              <div class="col-sm-12 text-right">
                <input type="submit" class="btn btn-default"/>
              </div>
            </div>

            <input type="hidden" name="override" value="1"/>
          </form>
        </div>
      </div>
    </div>
  </body>
</html>
