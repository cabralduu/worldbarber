CREATE DATABASE IF NOT EXISTS world_barber;
USE world_barber;

-- Tabela de serviços
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    duration INT NOT NULL,
    price DECIMAL(10,2) NOT NULL
);

-- Tabela de agendamentos ativos
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Tabela de agendamentos recebidos
CREATE TABLE IF NOT EXISTS received_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Carteira mensal
CREATE TABLE IF NOT EXISTS wallet (
    month VARCHAR(20) PRIMARY KEY,
    total DECIMAL(10,2) NOT NULL
);

-- Administradores
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Inserir admin inicial (senha: admin123)
INSERT INTO admins (username,password)
VALUES ('admin', MD5('admin123'));

