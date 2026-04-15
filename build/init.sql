DROP DATABASE IF EXISTS minibank;
CREATE DATABASE IF NOT EXISTS minibank;
USE minibank;

CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_name VARCHAR(100) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inseriamo un conto di default per semplificare i test (ID = 1, in EUR

-- Inserisco altri conti con valute diverse
INSERT INTO accounts (owner_name, currency) VALUES
('Luigi Bianchi', 'USD'),
('Anna Verdi', 'GBP'),
('Giovanni Neri', 'EUR'),
('Maria Gialli', 'USD'),
('Paolo Rossi', 'GBP');

INSERT INTO accounts (owner_name, currency) VALUES ('Mario Rossi', 'EUR');


CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    type ENUM('deposit', 'withdrawal') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    description VARCHAR(255),
    balance_after DECIMAL(15, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE
);

INSERT INTO transactions (account_id, type, amount, description, balance_after) VALUES
(1, 'deposit', 1000.00, 'Stipendio', 1000.00),
(1, 'withdrawal', 200.00, 'Spesa supermercato', 800.00),
(1, 'deposit', 500.00, 'Regalo', 1300.00),
(1, 'withdrawal', 100.00, 'Uscita con amici', 1200.00);

-- Movimenti per Luigi Bianchi (ID=2, USD)
INSERT INTO transactions (account_id, type, amount, description, balance_after) VALUES
(2, 'deposit', 1500.00, 'Salary', 1500.00),
(2, 'withdrawal', 300.00, 'Rent', 1200.00),
(2, 'deposit', 200.00, 'Bonus', 1400.00);

-- Movimenti per Anna Verdi (ID=3, GBP)
INSERT INTO transactions (account_id, type, amount, description, balance_after) VALUES
(3, 'deposit', 2000.00, 'Salary', 2000.00),
(3, 'withdrawal', 500.00, 'Shopping', 1500.00),
(3, 'deposit', 1000.00, 'Gift', 2500.00);

-- Movimenti per Giovanni Neri (ID=4, EUR)
INSERT INTO transactions (account_id, type, amount, description, balance_after) VALUES
(4, 'deposit', 3000.00, 'Salary', 3000.00),
(4, 'withdrawal', 1000.00, 'Bills', 2000.00),
(4, 'deposit', 500.00, 'Refund', 2500.00);

-- Movimenti per Maria Gialli (ID=5, USD)
INSERT INTO transactions (account_id, type, amount, description, balance_after) VALUES
(5, 'deposit', 5000.00, 'Salary', 5000.00),
(5, 'withdrawal', 1000.00, 'Car repair', 4000.00),
(5, 'deposit', 200.00, 'Interest', 4200.00);

-- Movimenti per Paolo Rossi (ID=6, GBP)
INSERT INTO transactions (account_id, type, amount, description, balance_after) VALUES
(6, 'deposit', 1800.00, 'Salary', 1800.00),
(6, 'withdrawal', 300.00, 'Food', 1500.00),
(6, 'deposit', 700.00, 'Bonus', 2200.00);

