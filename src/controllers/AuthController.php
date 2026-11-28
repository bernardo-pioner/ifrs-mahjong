<?php

declare(strict_types=1);

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->guestOnly();
        $this->view('auth/login', [
            'title' => 'Entrar',
            'user' => null,
            'currentPath' => '/login',
            'email' => '',
        ]);
    }

    public function login(): void
    {
        $this->guestOnly();
        verifyCsrf();

        $email = request('email');
        $password = $_POST['password'] ?? '';
        $password = is_string($password) ? $password : '';
        $resultado = AuthService::attempt($email, $password);

        if ($resultado === 'unverified') {
            flash('error', 'Confirme seu e-mail para entrar. O link foi enviado no cadastro.');
            $this->view('auth/login', [
                'title' => 'Entrar',
                'user' => null,
                'currentPath' => '/login',
                'email' => $email,
            ]);
            return;
        }

        if ($resultado !== 'ok') {
            flash('error', 'E-mail ou senha inválidos.');
            $this->view('auth/login', [
                'title' => 'Entrar',
                'user' => null,
                'currentPath' => '/login',
                'email' => $email,
            ]);
            return;
        }

        redirect('/');
    }

    public function showRegister(): void
    {
        $this->guestOnly();
        $this->view('auth/register', [
            'title' => 'Cadastro',
            'user' => null,
            'currentPath' => '/cadastro',
            'username' => '',
            'email' => '',
            'notifyEmails' => true,
            'passwordMismatch' => false,
            'registerResponse' => null,
        ]);
    }

    public function register(): void
    {
        $this->guestOnly();
        verifyCsrf();

        $username = request('username');
        $email = request('email');
        $password = $_POST['password'] ?? '';
        $password = is_string($password) ? $password : '';
        $passwordConfirm = $_POST['password_confirmation'] ?? '';
        $passwordConfirm = is_string($passwordConfirm) ? $passwordConfirm : '';
        $notifyEmails = isset($_POST['emails_notificacao']);

        if (!hash_equals($password, $passwordConfirm)) {
            $this->view('auth/register', [
                'title' => 'Cadastro',
                'user' => null,
                'currentPath' => '/cadastro',
                'username' => $username,
                'email' => $email,
                'notifyEmails' => $notifyEmails,
                'passwordMismatch' => true,
                'registerResponse' => null,
            ]);
            return;
        }

        $registerResponse = User::register($username, $email, $password, $notifyEmails);
        if ($registerResponse === 'mail') {
            flash('error', 'Não foi possível enviar o e-mail de confirmação. Tente novamente.');
            $this->view('auth/register', [
                'title' => 'Cadastro',
                'user' => null,
                'currentPath' => '/cadastro',
                'username' => $username,
                'email' => $email,
                'notifyEmails' => $notifyEmails,
                'passwordMismatch' => false,
                'registerResponse' => null,
            ]);
            return;
        }

        if ($registerResponse !== true) {
            $this->view('auth/register', [
                'title' => 'Cadastro',
                'user' => null,
                'currentPath' => '/cadastro',
                'username' => $username,
                'email' => $email,
                'notifyEmails' => $notifyEmails,
                'passwordMismatch' => false,
                'registerResponse' => false,
            ]);
            return;
        }

        flash('success', 'Cadastro feito. Enviamos um link de confirmação para o seu e-mail. Confirme para entrar.');
        redirect('/login');
    }

    public function confirmEmail(): void
    {
        $resultado = EmailCode::consumeVerificationToken(request('token'));
        if ($resultado === 'ok') {
            flash('success', 'E-mail confirmado. Agora você pode entrar.');
        } elseif ($resultado === 'already') {
            flash('success', 'Este e-mail já estava confirmado. Você pode entrar.');
        } else {
            flash('error', 'O link de confirmação é inválido ou expirou.');
        }

        redirect('/login');
    }

    public function logout(): void
    {
        $this->requireAuth();
        verifyCsrf();
        AuthService::logout();
        redirect('/login');
    }

    public function checkUsernameAvailability()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $username = request('username');

            $username_exists = User::usernameExists($username);

            $resposta = [
                'available' => !$username_exists
            ];
            http_response_code(200); // 200 - OK
        } catch (Exception $e) {
            $resposta = [
                'status' => 'error',
                'mensagem' => $e->getMessage()
            ];
            http_response_code(400); // 400 - Bad Request
        }
        echo json_encode($resposta);
        exit; 
    }

    public function checkEmailAvailability()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $email = request('email');

            $email_exists = User::emailExists($email);

            $resposta = [
                'available' => !$email_exists
            ];
            http_response_code(200); // 200 - OK
        } catch (Exception $e) {
            $resposta = [
                'status' => 'error',
                'mensagem' => $e->getMessage()
            ];
            http_response_code(400); // 400 - Bad Request
        }
        echo json_encode($resposta);
        exit; 
    }

    public function showForgotPassword(): void
    {
        $this->guestOnly();
        $this->view('auth/forgot-password', $this->forgotPasswordViewData());
    }

    public function forgotPassword(): void
    {
        $this->guestOnly();
        verifyCsrf();

        $action = request('acao');
        if ($action === 'reenviar') {
            $this->resendPasswordPin();
            return;
        }
        if ($action === 'verificar') {
            $this->verifyPasswordPin();
            return;
        }
        if ($action === 'salvar') {
            $this->saveNewPassword();
            return;
        }
        if ($action === 'reiniciar') {
            unset($_SESSION['password_reset']);
            redirect('/esqueci-senha');
        }

        $this->requestPasswordPin();
    }

    private function requestPasswordPin(): void
    {
        $email = strtolower(request('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->view('auth/forgot-password', $this->forgotPasswordViewData([
                'step' => 'email',
                'email' => $email,
                'error' => 'O e-mail informado deve estar em um formato válido.',
            ]));
            return;
        }

        $account = User::findByEmail($email);
        $user = $account !== null ? User::findById((int) $account['id']) : null;
        if ($user === null || !(int) $user['active']) {
            $this->view('auth/forgot-password', $this->forgotPasswordViewData([
                'step' => 'email',
                'email' => $email,
                'error' => 'Não encontramos uma conta com esse e-mail.',
            ]));
            return;
        }

        $userId = (int) $user['id'];
        $storedEmail = (string) $user['email'];
        if (EmailCode::secondsUntilRetry($userId, 'redefinir_senha') === 0) {
            $sent = ClubMail::codigoNovaSenha($userId);
            if (!$sent) {
                $this->view('auth/forgot-password', $this->forgotPasswordViewData([
                    'step' => 'email',
                    'email' => $email,
                    'error' => 'Não foi possível enviar o e-mail. Tente novamente.',
                ]));
                return;
            }
        }

        $this->rememberPasswordReset($userId, $storedEmail, 'pin');
        redirect('/esqueci-senha');
    }

    private function resendPasswordPin(): void
    {
        $state = $this->passwordResetState();
        if ($state === null || $state['step'] !== 'pin') {
            redirect('/esqueci-senha');
        }

        $userId = (int) $state['user_id'];
        $wait = EmailCode::secondsUntilRetry($userId, 'redefinir_senha');
        if ($wait > 0) {
            $this->view('auth/forgot-password', $this->forgotPasswordViewData([
                'error' => 'Aguarde ' . $wait . ' segundos para reenviar o e-mail.',
            ]));
            return;
        }

        if (!ClubMail::codigoNovaSenha($userId)) {
            $this->view('auth/forgot-password', $this->forgotPasswordViewData([
                'error' => 'Não foi possível reenviar o e-mail. Tente novamente.',
            ]));
            return;
        }

        redirect('/esqueci-senha');
    }

    private function verifyPasswordPin(): void
    {
        $state = $this->passwordResetState();
        if ($state === null || $state['step'] !== 'pin') {
            redirect('/esqueci-senha');
        }

        $digits = $_POST['pin'] ?? [];
        $code = '';
        if (is_array($digits)) {
            foreach ($digits as $digit) {
                if (is_string($digit)) {
                    $code .= $digit;
                }
            }
        }
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            $this->view('auth/forgot-password', $this->forgotPasswordViewData([
                'error' => 'Digite os 6 números do PIN.',
            ]));
            return;
        }

        $result = EmailCode::consumePasswordCode((int) $state['user_id'], $code);
        if ($result === 'locked') {
            $this->view('auth/forgot-password', $this->forgotPasswordViewData([
                'error' => 'Muitas tentativas. Reenvie o e-mail para receber outro PIN.',
            ]));
            return;
        }
        if ($result !== 'ok') {
            $this->view('auth/forgot-password', $this->forgotPasswordViewData([
                'error' => 'PIN incorreto ou expirado.',
            ]));
            return;
        }

        $this->rememberPasswordReset((int) $state['user_id'], (string) $state['email'], 'password');
        redirect('/esqueci-senha');
    }

    private function saveNewPassword(): void
    {
        $state = $this->passwordResetState();
        if ($state === null || $state['step'] !== 'password') {
            redirect('/esqueci-senha');
        }

        $password = $_POST['password'] ?? '';
        $password = is_string($password) ? $password : '';
        $passwordConfirm = $_POST['password_confirmation'] ?? '';
        $passwordConfirm = is_string($passwordConfirm) ? $passwordConfirm : '';

        $passwordError = '';
        $passwordConfirmError = '';
        if (mb_strlen($password) < 8 || mb_strlen($password) > 128 || preg_match('/\d/', $password) !== 1) {
            $passwordError = 'A senha deve ter no mínimo 8 caracteres e conter pelo menos um número';
        }
        if (!hash_equals($password, $passwordConfirm)) {
            $passwordConfirmError = 'As senhas não combinam.';
        }
        if ($passwordError !== '' || $passwordConfirmError !== '') {
            $this->view('auth/forgot-password', $this->forgotPasswordViewData([
                'passwordError' => $passwordError,
                'passwordConfirmError' => $passwordConfirmError,
            ]));
            return;
        }

        if (!User::updatePassword((int) $state['user_id'], $password)) {
            $this->view('auth/forgot-password', $this->forgotPasswordViewData([
                'error' => 'Não foi possível salvar a senha. Tente novamente.',
            ]));
            return;
        }

        unset($_SESSION['password_reset']);
        session_regenerate_id(true);
        flash('success', 'Senha atualizada. Entre com a nova senha.');
        redirect('/login');
    }

    /** @param array<string, mixed> $overrides */
    private function forgotPasswordViewData(array $overrides = []): array
    {
        $state = $this->passwordResetState();
        $step = $state['step'] ?? 'email';
        $email = (string) ($state['email'] ?? '');
        $resendWait = 0;
        if ($state !== null && $step === 'pin') {
            $resendWait = EmailCode::secondsUntilRetry((int) $state['user_id'], 'redefinir_senha');
        }

        $data = [
            'title' => 'Esqueci minha senha',
            'user' => null,
            'currentPath' => '/esqueci-senha',
            'step' => $step,
            'email' => $email,
            'error' => '',
            'resendWait' => $resendWait,
            'passwordError' => '',
            'passwordConfirmError' => '',
        ];

        return array_merge($data, $overrides);
    }

    /** @return array{user_id: int, email: string, step: string}|null */
    private function passwordResetState(): ?array
    {
        $state = $_SESSION['password_reset'] ?? null;
        if (!is_array($state)) {
            return null;
        }

        $userId = (int) ($state['user_id'] ?? 0);
        $email = (string) ($state['email'] ?? '');
        $step = (string) ($state['step'] ?? '');
        if ($userId <= 0 || !in_array($step, ['pin', 'password'], true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            unset($_SESSION['password_reset']);
            return null;
        }

        $user = User::findById($userId);
        if ($user === null || !(int) $user['active'] || strcasecmp((string) $user['email'], $email) !== 0) {
            unset($_SESSION['password_reset']);
            return null;
        }

        return [
            'user_id' => $userId,
            'email' => (string) $user['email'],
            'step' => $step,
        ];
    }

    private function rememberPasswordReset(int $userId, string $email, string $step): void
    {
        $_SESSION['password_reset'] = [
            'user_id' => $userId,
            'email' => $email,
            'step' => $step,
        ];
    }
}