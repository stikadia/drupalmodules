# Custom API Module

## Description

This module provides custom API endpoints in Drupal for user authentication and data listing. It utilizes Symfony routing and custom controllers to handle incoming requests and provide appropriate responses.

## Installation

1. Download and install the module in your Drupal site's `modules/custom` directory.
2. Enable the Custom API module through the Drupal admin interface or using Drush (`drush en custom_api`).

## Endpoints

### Get Token

- **Endpoint:** `/ia-api/getToken`
- **Method:** POST
- **Request Parameters:**
  - `username`: Username for authentication.
  - `password`: Password for authentication.
- **Sample Request:**
  ```json
  {
    "username": "example_user",
    "password": "example_password"
  }
