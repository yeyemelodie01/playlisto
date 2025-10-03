<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251003141541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE recommendation DROP FOREIGN KEY FK_433224D26BBD148
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE recommendation DROP FOREIGN KEY FK_433224D2A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE recommendation
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE track CHANGE artist artists JSON NOT NULL COMMENT '(DC2Type:json)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE recommendation (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, playlist_id INT DEFAULT NULL, generated_at DATETIME NOT NULL, source VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_433224D2A76ED395 (user_id), INDEX IDX_433224D26BBD148 (playlist_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE recommendation ADD CONSTRAINT FK_433224D26BBD148 FOREIGN KEY (playlist_id) REFERENCES playlist (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE recommendation ADD CONSTRAINT FK_433224D2A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE track CHANGE artists artist JSON NOT NULL COMMENT '(DC2Type:json)'
        SQL);
    }
}
