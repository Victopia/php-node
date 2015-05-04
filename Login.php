<?php
/*! Login.php | Simple user login. */

use framework\Session;

$req = $this->request;
$res = $this->response;
if ( $req->user ) {
  $res->redirect($req->client('referer'));
  die;
}

if ( $req->post('uid') && $req->post('pwd') ) {
  $ret = @Session::validate($req->post('uid'), $req->post('pwd'), @$req->post('override'));

  if ( is_string($ret) ) {
    setcookie('sid', $ret);

    $res->redirect(
      $req->param('returnUrl')
      );
    die;
  }
  else {
    switch ( $ret ) {
      case Session::ERR_MISMATCH:
        $ret = 'Username and password mismatch';
        break;

      default:
      case Session::ERR_EXISTS:
        $ret = 'You have not logged out from last session, login again to override.';
        break;
    }
  }
}

unset($req, $res);
?>
<!doctype html>

<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <title></title>

    <!-- jQuery -->
    <script src="//code.jquery.com/jquery.min.js"></script>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap-theme.min.css"/>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
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
                <input type="text" name="uid" id="txtUID" class="form-control"/>
              </div>
            </div>

            <div class="form-group">
              <label for="txtPWD" class="col-sm-3 control-label">Password</label>
              <div class="col-sm-9">
                <input type="password" name="pwd" id="txtPWD" class="form-control"/>
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
