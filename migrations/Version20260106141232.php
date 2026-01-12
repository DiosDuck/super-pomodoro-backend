<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106141232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE token_verification DROP INDEX UNIQ_4A078E0364B64DCC, ADD INDEX IDX_4A078E0364B64DCC (userId)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE token_verification DROP INDEX IDX_4A078E0364B64DCC, ADD UNIQUE INDEX UNIQ_4A078E0364B64DCC (userId)');
    }
}
