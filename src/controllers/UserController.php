<?php

declare(strict_types=1);

final class UserController extends Controller
{
    public function index(): void
    {
        $user = $this->requireAdmin();
        $this->view('users/index', [
            'title' => 'Usuários',
            'user' => $user,
            'currentPath' => '/usuarios',
            'users' => User::all(),
        ]);
    }

    public function bio(): void
    {
        $user = $this->requireAuth();
        $this->view('users/bio', [
            'title' => 'Configurações de Bio',
            'user' => $user,
            'currentPath' => '/perfil',
            'nickname' => (string) ($user['nickname'] ?? ''),
            'realName' => (string) ($user['name'] ?? ''),
            'bio' => (string) ($user['bio'] ?? ''),
            'errors' => [],
        ]);
    }

    public function updateBio(): void
    {
        $user = $this->requireAuth();
        verifyCsrf();

        $nickname = request('nickname');
        $realName = request('real_name');
        $bio = request('bio');
        $errors = User::bioErrors($nickname, $realName, $bio);
        $photo = null;

        if ($errors === []) {
            $upload = $this->storeProfilePhoto($user);
            if ($upload['error'] !== null) {
                $errors[] = $upload['error'];
            } else {
                $photo = $upload['path'];
            }
        }

        if ($errors === []) {
            $errors = User::updateBio((int) $user['id'], $nickname, $realName, $bio, $photo);
        }

        if ($errors !== []) {
            $this->view('users/bio', [
                'title' => 'Configurações de Bio',
                'user' => $user,
                'currentPath' => '/perfil',
                'nickname' => $nickname,
                'realName' => $realName,
                'bio' => $bio,
                'errors' => $errors,
            ]);
            return;
        }

        flash('success', 'Perfil atualizado.');
        redirect('/perfil');
    }

    /** @return array{path: ?string, error: ?string} */
    private function storeProfilePhoto(array $user): array
    {
        if (!isset($_FILES['photo']) || !is_array($_FILES['photo'])) {
            return ['path' => null, 'error' => null];
        }

        $error = (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'Não foi possível enviar a foto. Tente novamente.'];
        }

        $tmp = (string) ($_FILES['photo']['tmp_name'] ?? '');
        $size = (int) ($_FILES['photo']['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0) {
            return ['path' => null, 'error' => 'Arquivo de foto inválido.'];
        }

        if ($size > 2 * 1024 * 1024) {
            return ['path' => null, 'error' => 'A foto deve ter no máximo 2 MB.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if (!isset($extensions[$mime])) {
            return ['path' => null, 'error' => 'Use uma imagem JPG, PNG, WEBP ou GIF.'];
        }

        $directory = ROOT_PATH . '/public/uploads/avatars';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return ['path' => null, 'error' => 'Não foi possível salvar a foto.'];
        }

        $filename = (int) $user['id'] . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
        $destination = $directory . '/' . $filename;
        if (!move_uploaded_file($tmp, $destination)) {
            return ['path' => null, 'error' => 'Não foi possível salvar a foto.'];
        }

        $previous = userPhotoUrl($user);
        if ($previous !== null) {
            $previousPath = ROOT_PATH . '/public' . $previous;
            if (is_file($previousPath)) {
                unlink($previousPath);
            }
        }

        return ['path' => '/uploads/avatars/' . $filename, 'error' => null];
    }
}
