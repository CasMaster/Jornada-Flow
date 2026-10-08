<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class DeployApprovalController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(): View
    {
        $environment = $this->environment();
        $pending = [];
        $directory = config('deploy_approval.pending_dir');

        if ($environment && is_dir($directory)) {
            foreach (scandir($directory) ?: [] as $name) {
                if (! preg_match('/^'.preg_quote($environment, '/').'-([a-f0-9]{64})$/D', $name, $matches)) {
                    continue;
                }
                $expires = $this->pendingExpiry($directory.DIRECTORY_SEPARATOR.$name);
                if ($expires > time()) {
                    $pending[] = ['digest' => $matches[1], 'expires' => $expires];
                }
            }
        }

        return view('admin.deploy', compact('environment', 'pending'));
    }

    public function approve(Request $request, string $digest): RedirectResponse
    {
        $environment = $this->environment();
        abort_unless($environment && preg_match('/^[a-f0-9]{64}$/D', $digest), 404);

        if ($request->session()->get('auth_provider') === 'oidc') {
            abort_unless(
                (int) $request->session()->get('oidc_authenticated_at', 0) >= now()->subMinutes(10)->timestamp,
                403,
                'Entre novamente com sua conta corporativa antes de aprovar o pacote.'
            );
        } else {
            $data = $request->validate(['password' => ['required', 'string']]);
            if (! $request->user()->active || ! Hash::check($data['password'], $request->user()->password)) {
                return back()->withErrors(['password' => 'Senha inválida.']);
            }
        }

        $pending = config('deploy_approval.pending_dir').DIRECTORY_SEPARATOR.$environment.'-'.$digest;
        abort_unless($this->pendingExpiry($pending) > time(), 409, 'Pacote expirado ou não encontrado.');

        $approvedDir = config('deploy_approval.approved_dir');
        abort_unless(is_dir($approvedDir) && is_writable($approvedDir), 503, 'Aprovação web indisponível.');
        $expires = min($this->pendingExpiry($pending), time() + 300);
        $actor = $request->user()->id;
        $signature = hash_hmac('sha256', "$environment:$digest:$expires:$actor", config('deploy_approval.key'));
        $approval = json_encode(compact('expires', 'actor', 'signature'), JSON_THROW_ON_ERROR);
        $path = $approvedDir.DIRECTORY_SEPARATOR.$environment.'-'.$digest;
        $file = @fopen($path, 'x');
        abort_unless($file !== false, 409, 'Este pacote já foi aprovado.');
        try {
            if (fwrite($file, $approval) !== strlen($approval)) {
                throw new \RuntimeException('Failed to write deployment approval.');
            }
        } catch (\Throwable $exception) {
            fclose($file);
            @unlink($path);
            throw $exception;
        }
        fclose($file);
        $this->audit->record('deployment.approved', null, [], ['environment' => $environment, 'digest' => $digest]);

        return redirect()->route('admin.deploy.index')->with('success', 'Pacote aprovado. Acompanhe o resultado no job de deploy.');
    }

    private function environment(): ?string
    {
        $environment = config('deploy_approval.environment');
        $key = config('deploy_approval.key');

        return in_array($environment, ['homologacao', 'producao'], true) && is_string($key) && strlen($key) >= 32
            ? $environment
            : null;
    }

    private function pendingExpiry(string $path): int
    {
        if (! is_file($path) || is_link($path)) {
            return 0;
        }

        $value = trim((string) @file_get_contents($path));

        return preg_match('/^[0-9]{10}$/D', $value) ? (int) $value : 0;
    }
}
