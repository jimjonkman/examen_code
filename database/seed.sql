-- Voorbeeldbedrijven voor lokaal ontwikkelen. Er zijn nog geen gebruikersaccounts.
SET NAMES utf8mb4;
SET time_zone = '+00:00';
INSERT INTO companies (name, slug, mail_from_name, mail_from_address, is_active)
VALUES
    ('Scabro', 'scabro', 'Scabro recruitment', 'recruitment@scabro.test', 1),
    ('Voorbeeldbedrijf', 'demo', 'Voorbeeldbedrijf recruitment', 'recruitment@demo.test', 1);
