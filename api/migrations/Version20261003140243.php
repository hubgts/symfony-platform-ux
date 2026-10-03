<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003140243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pokemon ADD evolution_condition VARCHAR(160) DEFAULT NULL');
        // Valeur par défaut le temps de remplir les types existants (réimportés juste après).
        $this->addSql("ALTER TABLE pokemon_type ADD defense JSONB DEFAULT '{}' NOT NULL");
        $this->addSql('ALTER TABLE pokemon_type ALTER defense DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pokemon DROP evolution_condition');
        $this->addSql('ALTER TABLE pokemon_type DROP defense');
    }
}
