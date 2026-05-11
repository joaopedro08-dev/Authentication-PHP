-- Database: auth_php
-- SQL Server 2016+

-- Create database if not exists
IF NOT EXISTS (SELECT name FROM sys.databases WHERE name = 'auth_php')
BEGIN
    CREATE DATABASE auth_php;
END
GO

USE auth_php;
GO

-- =============================================
-- TABLE: users
-- =============================================
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='users' AND xtype='U')
BEGIN
    CREATE TABLE users (
        id BIGINT PRIMARY KEY IDENTITY(1,1),
        name NVARCHAR(255) NOT NULL,
        email NVARCHAR(255) NOT NULL UNIQUE,
        password_hash NVARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT GETUTCDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETUTCDATE(),
        CONSTRAINT uk_users_email UNIQUE (email)
    );
    
    CREATE INDEX idx_users_email ON users(email);
    CREATE INDEX idx_users_created_at ON users(created_at);
    CREATE INDEX idx_users_updated_at ON users(updated_at);
END
GO

-- =============================================
-- TABLE: refresh_tokens
-- =============================================
IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='refresh_tokens' AND xtype='U')
BEGIN
    CREATE TABLE refresh_tokens (
        id BIGINT PRIMARY KEY IDENTITY(1,1),
        user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
        token NVARCHAR(255) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT GETUTCDATE(),
        CONSTRAINT fk_refresh_tokens_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
    );
    
    CREATE INDEX idx_refresh_tokens_user_id ON refresh_tokens(user_id);
    CREATE INDEX idx_refresh_tokens_token ON refresh_tokens(token);
    CREATE INDEX idx_refresh_tokens_expires_at ON refresh_tokens(expires_at);
END
GO
