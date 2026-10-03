<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003132655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pokemon (name VARCHAR(100) NOT NULL, genus VARCHAR(100) NOT NULL, description TEXT NOT NULL, height INT NOT NULL, weight INT NOT NULL, legendary BOOLEAN NOT NULL, mythical BOOLEAN NOT NULL, hp INT NOT NULL, attack INT NOT NULL, defense INT NOT NULL, special_attack INT NOT NULL, special_defense INT NOT NULL, speed INT NOT NULL, base_stat_total INT NOT NULL, sprite VARCHAR(255) NOT NULL, artwork VARCHAR(255) NOT NULL, id INT NOT NULL, slug VARCHAR(100) NOT NULL, evolves_from_id INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_62DC90F3989D9B62 ON pokemon (slug)');
        $this->addSql('CREATE INDEX pokemon_name_idx ON pokemon (name)');
        $this->addSql('CREATE INDEX IDX_62DC90F331CF6115 ON pokemon (evolves_from_id)');
        $this->addSql('CREATE TABLE pokemon_pokemon_type (pokemon_id INT NOT NULL, pokemon_type_code VARCHAR(20) NOT NULL, PRIMARY KEY (pokemon_id, pokemon_type_code))');
        $this->addSql('CREATE INDEX IDX_F1F052B32FE71C3E ON pokemon_pokemon_type (pokemon_id)');
        $this->addSql('CREATE INDEX IDX_F1F052B391F31FB6 ON pokemon_pokemon_type (pokemon_type_code)');
        $this->addSql('CREATE TABLE pokemon_type (code VARCHAR(20) NOT NULL, name VARCHAR(50) NOT NULL, color VARCHAR(7) NOT NULL, PRIMARY KEY (code))');
        $this->addSql('CREATE TABLE team (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, name VARCHAR(80) NOT NULL, trainer VARCHAR(80) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE team_member (team_id UUID NOT NULL, pokemon_id INT NOT NULL, PRIMARY KEY (team_id, pokemon_id))');
        $this->addSql('CREATE INDEX IDX_6FFBDA1296CD8AE ON team_member (team_id)');
        $this->addSql('CREATE INDEX IDX_6FFBDA12FE71C3E ON team_member (pokemon_id)');
        $this->addSql('ALTER TABLE pokemon ADD CONSTRAINT FK_62DC90F331CF6115 FOREIGN KEY (evolves_from_id) REFERENCES pokemon (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE pokemon_pokemon_type ADD CONSTRAINT FK_F1F052B32FE71C3E FOREIGN KEY (pokemon_id) REFERENCES pokemon (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pokemon_pokemon_type ADD CONSTRAINT FK_F1F052B391F31FB6 FOREIGN KEY (pokemon_type_code) REFERENCES pokemon_type (code) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE team_member ADD CONSTRAINT FK_6FFBDA1296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_member ADD CONSTRAINT FK_6FFBDA12FE71C3E FOREIGN KEY (pokemon_id) REFERENCES pokemon (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pokemon DROP CONSTRAINT FK_62DC90F331CF6115');
        $this->addSql('ALTER TABLE pokemon_pokemon_type DROP CONSTRAINT FK_F1F052B32FE71C3E');
        $this->addSql('ALTER TABLE pokemon_pokemon_type DROP CONSTRAINT FK_F1F052B391F31FB6');
        $this->addSql('ALTER TABLE team_member DROP CONSTRAINT FK_6FFBDA1296CD8AE');
        $this->addSql('ALTER TABLE team_member DROP CONSTRAINT FK_6FFBDA12FE71C3E');
        $this->addSql('DROP TABLE pokemon');
        $this->addSql('DROP TABLE pokemon_pokemon_type');
        $this->addSql('DROP TABLE pokemon_type');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE team_member');
    }
}
