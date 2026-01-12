<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251229084853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pomodoro_settings CHANGE userId userId INT NOT NULL');
        $this->addSql('ALTER TABLE pomodoro_settings ADD CONSTRAINT FK_412BDF3664B64DCC FOREIGN KEY (userId) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_412BDF3664B64DCC ON pomodoro_settings (userId)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pomodoro_settings DROP FOREIGN KEY FK_412BDF3664B64DCC');
        $this->addSql('DROP INDEX UNIQ_412BDF3664B64DCC ON pomodoro_settings');
        $this->addSql('ALTER TABLE pomodoro_settings CHANGE userId userId VARCHAR(255) NOT NULL');
    }
}
