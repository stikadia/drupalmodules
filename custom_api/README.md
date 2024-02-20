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
- **Sample Response:**
  ```json
  {
    "uid": 123,
    "token": "generated_token"
  }

### List Data API 

- **Endpoint:** `/ia-api/listdata/{content_type}`
- **Method:** GET
- **Request Parameters:**
  - `token`: Authentication token for accessing the API.
  - `uid`: User ID associated with the token for additional validation.
  - `page` (optional): Page number for paginated results (default: 1).
  - `limit` (optional): Number of items per page (default: 10).
  - `sort` (optional): Field to sort by (default: 'created').
  - `direction` (optional): Sorting direction ('asc' or 'desc') (default: 'asc').
  - `fields` (optional): Comma-separated list of fields to include in the response.
- **Sample Request:**
  `GET /ia-api/listdata/articles?token=your_token&uid=123&page=1&limit=10&sort=title&direction=asc&fields=title,body,image`
- **Sample Response:**
  ```json
  {
    "total_rows": 20,
    "nextPage": "/ia-api/listdata/articles?page=2&limit=10&sort=title&direction=asc",
    "total_pages": 2,
    "prevPage": null,
    "data": [
      {
        "title": "Sample Article 1",
        "body": "Lorem ipsum dolor sit amet...",
        "image": {
          "url": "https://example.com/image.jpg"
        }
      },
      {
        "title": "Sample Article 2",
        "body": "Lorem ipsum dolor sit amet...",
        "image": {
          "url": "https://example.com/image2.jpg"
        }
      },
      ...
    ]
  }

## Dependencies
- Drupal core
- Symfony Routing Component

## Author

This module is authored by Sharad Tikadia.


  
