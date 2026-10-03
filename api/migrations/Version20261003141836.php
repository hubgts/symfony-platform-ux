<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003141836 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pokemon ADD evolution_stage INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE pokemon ADD fully_evolved BOOLEAN DEFAULT true NOT NULL');
        $this->addSql('CREATE INDEX pokemon_generation_idx ON pokemon (generation)');
        $this->addSql('CREATE INDEX pokemon_base_stat_total_idx ON pokemon (base_stat_total)');
        $this->addSql('ALTER TABLE pokemon_type ADD pokemon_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX pokemon_generation_idx');
        $this->addSql('DROP INDEX pokemon_base_stat_total_idx');
        $this->addSql('ALTER TABLE pokemon DROP evolution_stage');
        $this->addSql('ALTER TABLE pokemon DROP fully_evolved');
        $this->addSql('ALTER TABLE pokemon_type DROP pokemon_count');
    }
}
