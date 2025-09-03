<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250903081839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE survey_answer (id INT AUTO_INCREMENT NOT NULL, submission_id INT NOT NULL, question_id INT NOT NULL, option_id INT NOT NULL, INDEX IDX_F2D38249E1FD4933 (submission_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE survey_submission (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, survey_id INT NOT NULL, deduced_mood VARCHAR(255) DEFAULT NULL, deduced_activity VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_9E7F50C4A76ED395 (user_id), UNIQUE INDEX uniq_survey_user (survey_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE survey_answer ADD CONSTRAINT FK_F2D38249E1FD4933 FOREIGN KEY (submission_id) REFERENCES survey_submission (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE survey_submission ADD CONSTRAINT FK_9E7F50C4A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE survey_answer DROP FOREIGN KEY FK_F2D38249E1FD4933
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE survey_submission DROP FOREIGN KEY FK_9E7F50C4A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE survey_answer
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE survey_submission
        SQL);
    }
}
