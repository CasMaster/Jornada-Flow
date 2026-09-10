#!/bin/sh
set -eu

USER_NAME="${PODMAN_USER:-$(id -un)}"
if command -v loginctl >/dev/null 2>&1; then
    loginctl show-user "$USER_NAME" >/dev/null 2>&1 || { echo "Usuário $USER_NAME não encontrado." >&2; exit 1; }
    if [ "$(id -u)" -eq 0 ]; then
        loginctl enable-linger "$USER_NAME"
    else
        echo "Execute 'sudo loginctl enable-linger $USER_NAME' uma vez para manter serviços após reboot."
    fi
fi
systemctl --user enable --now podman-restart.service
systemctl --user is-enabled podman-restart.service
echo 'Reinício automático do Podman habilitado. Valide em uma janela de manutenção.'
