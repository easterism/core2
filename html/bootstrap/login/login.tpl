<form class="form-signin" method="post">
    <!-- BEGIN logo -->
    <div class="container-fluid logo-container">
        <img src="{logo}" alt="logo"/>
    </div>
    <!-- END logo -->
    <!-- BEGIN error -->
        <p class="error">[ERROR_MSG]</p>
    <!-- END error -->
    <input name="action" type="hidden" />
    <label for="input-login" class="sr-only">Логин или email</label>
    <input type="text" name="login" id="input-login" class="form-control" placeholder="Логин или email" required autofocus value="[ERROR_LOGIN]">
    <label for="gfhjkm" class="sr-only">Пароль</label>
    <input type="password" name="password" id="gfhjkm" class="form-control" placeholder="Пароль" required>
    <button class="btn btn-lg btn-success btn-block" type="submit">Войти</button>
</form>