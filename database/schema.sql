-- PortionPro Database Schema
-- Food Costing Calculator for Small Food Businesses

CREATE DATABASE IF NOT EXISTS portionpro;
USE portionpro;

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255),
    google_id VARCHAR(50) UNIQUE,
    picture VARCHAR(255),
    business_name VARCHAR(100),
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Ingredients table
CREATE TABLE ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    price_per_unit DECIMAL(10, 4) NOT NULL,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Recipes table
CREATE TABLE recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    servings INT NOT NULL DEFAULT 1,
    profit_margin DECIMAL(5, 2) NOT NULL DEFAULT 30.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Recipe ingredients junction table
CREATE TABLE recipe_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10, 4) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
);

-- Unit conversions table for smart converter
CREATE TABLE unit_conversions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_unit VARCHAR(20) NOT NULL,
    to_unit VARCHAR(20) NOT NULL,
    conversion_factor DECIMAL(10, 6) NOT NULL,
    category VARCHAR(50) NOT NULL
);

-- Insert common unit conversions
INSERT INTO unit_conversions (from_unit, to_unit, conversion_factor, category) VALUES
-- Weight conversions (to grams)
('kg', 'g', 1000, 'weight'),
('lb', 'g', 453.592, 'weight'),
('oz', 'g', 28.3495, 'weight'),
('g', 'g', 1, 'weight'),

-- Volume conversions (to ml)
('l', 'ml', 1000, 'volume'),
('gal', 'ml', 3785.41, 'volume'),
('qt', 'ml', 946.353, 'volume'),
('pt', 'ml', 473.176, 'volume'),
('cup', 'ml', 236.588, 'volume'),
('tbsp', 'ml', 14.7868, 'volume'),
('tsp', 'ml', 4.92892, 'volume'),
('ml', 'ml', 1, 'volume'),

-- Count conversions
('dozen', 'piece', 12, 'count'),
('piece', 'piece', 1, 'count');

-- Create indexes for better performance
CREATE INDEX idx_ingredients_user_id ON ingredients(user_id);
CREATE INDEX idx_recipes_user_id ON recipes(user_id);
CREATE INDEX idx_recipe_ingredients_recipe_id ON recipe_ingredients(recipe_id);
CREATE INDEX idx_recipe_ingredients_ingredient_id ON recipe_ingredients(ingredient_id);
