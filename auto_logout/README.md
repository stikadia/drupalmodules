# Auto Logout Module

This module provides functionality to automatically log out users after a specified period of inactivity. The main feature of the module is the ability to configure the session timeout duration through a user-friendly settings form.

## Usage

### Installation
1. Download and install the module in your Drupal project's `modules` directory.
2. Enable the module via the Drupal administration interface or using Drush (`drush en auto_logout`).

### Configuration
1. Navigate to Configuration > People > Auto Logout (admin/config/people/auto_logout) in the Drupal administration interface.
2. Set the desired session timeout duration in seconds using the provided form field.
3. Save the configuration.

### Functionality
- Users will be automatically logged out after the specified period of inactivity.
- The session timeout duration can be adjusted as needed through the configuration settings.

## Code Explanation

The `AutoLogoutSettingsForm` class defines the form used for configuring the session timeout duration. Here's a brief overview of its key methods:
- `getFormId()`: Returns the unique ID of the form.
- `getEditableConfigNames()`: Returns the names of the configuration objects that the form can edit.
- `buildForm()`: Constructs the form elements, including a number field for setting the session timeout.
- `validateForm()`: Validates the form submission, ensuring that the session timeout value is greater than zero.
- `submitForm()`: Saves the form submission by setting and saving the session timeout configuration value.

## Author

This module is authored by Sharad Tikadia.

