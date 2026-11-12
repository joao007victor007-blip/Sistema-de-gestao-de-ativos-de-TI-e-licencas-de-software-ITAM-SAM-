<?php

namespace App\Enums;

enum AuditAction: string
{
    // Registradas automaticamente nas edições de cadastro
    case Created = 'criou';
    case Updated = 'alterou';

    // Ações de negócio
    case Checkout = 'entrega';
    case Checkin = 'devolucao';
    case TermSigned = 'termo';
    case LicenseAssigned = 'atribuicao';
    case LicenseRevoked = 'liberacao';
    case Disposed = 'descarte';
    case Retired = 'baixa';
    case Terminated = 'desligamento';
    case Reactivated = 'reativacao';
    case KeyRevealed = 'chave';
    case PasswordReset = 'senha';
    case Imported = 'importacao';
    case Renewed = 'renovacao';
    case AttachmentAdded = 'anexo';
    case AttachmentRemoved = 'anexo_removido';

    // Backups
    case BackupCreated = 'backup';
    case BackupDownloaded = 'backup_download';

    // Acesso ao sistema
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_falhou';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Cadastro',
            self::Updated => 'Alteração',
            self::Checkout => 'Entrega de equipamento',
            self::Checkin => 'Devolução de equipamento',
            self::TermSigned => 'Termo assinado',
            self::LicenseAssigned => 'Atribuição de licença',
            self::LicenseRevoked => 'Liberação de licença',
            self::Disposed => 'Descarte',
            self::Retired => 'Baixa de licença',
            self::Terminated => 'Desligamento',
            self::Reactivated => 'Reativação',
            self::KeyRevealed => 'Visualização de chave',
            self::PasswordReset => 'Redefinição de senha',
            self::Imported => 'Importação de planilha',
            self::Renewed => 'Renovação automática',
            self::AttachmentAdded => 'Anexo incluído',
            self::AttachmentRemoved => 'Anexo removido',
            self::BackupCreated => 'Backup gerado manualmente',
            self::BackupDownloaded => 'Download de backup',
            self::Login => 'Entrada no sistema',
            self::Logout => 'Saída do sistema',
            self::LoginFailed => 'Tentativa de login falha',
        };
    }
}