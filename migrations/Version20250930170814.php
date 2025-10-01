<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250930170814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE survey_answer DROP FOREIGN KEY FK_F2D38249E1FD4933
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_F2D38249E1FD4933 ON survey_answer
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE survey_answer CHANGE option_id option_id INT DEFAULT NULL, CHANGE option_value option_value VARCHAR(255) DEFAULT NULL, CHANGE submission_id survey_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE survey_answer ADD CONSTRAINT FK_F2D38249B3FE509D FOREIGN KEY (survey_id) REFERENCES survey_submission (id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F2D38249B3FE509D ON survey_answer (survey_id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_survey_user ON survey_submission
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_survey_submission_survey ON survey_submission (survey_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE survey_submission RENAME INDEX idx_9e7f50c4a76ed395 TO idx_survey_submission_user
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP INDEX idx_survey_submission_survey ON survey_submission
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_survey_user ON survey_submission (survey_id, user_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE survey_submission RENAME INDEX idx_survey_submission_user TO IDX_9E7F50C4A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE survey_answer DROP FOREIGN KEY FK_F2D38249B3FE509D
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_F2D38249B3FE509D ON survey_answer
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE survey_answer CHANGE option_id option_id INT NOT NULL, CHANGE option_value option_value VARCHAR(255) NOT NULL, CHANGE survey_id submission_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE survey_answer ADD CONSTRAINT FK_F2D38249E1FD4933 FOREIGN KEY (submission_id) REFERENCES survey_submission (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F2D38249E1FD4933 ON survey_answer (submission_id)
        SQL);
    }
}
