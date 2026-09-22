# Coding Standards
- Always use objective-coding practices.
- Use php as the primary programming language.
- Use javascript if the functionality cannot be implemented in php.


# Formatting & Style
- Use descriptive, CamelCase names for variables.
- Write explicit error-handling blocks using try/catch.

# Architecture
- Follow the clean architecture pattern.


- Read the file /install/structure.sql to understand the database structure and relationships.
- When adding an iamge, use the /images folder and reference it in the code using a relative path.
- when adding an image, save the image as driver-<driver_id>.jpg or brand-<brand_id>.jpg, where <driver_id> and <brand_id> are the respective IDs of the driver or brand in the database.
- Check if the codebase is secure, so no sql injection or PHP code injection
- After every change update /doc/update-log.md with a description of the change, the date, and the author of the change.
- If an object gets deleted, ask for a random 4 digit so you don't delete an object by mistake. If the 4 digit is not correct, do not delete the object.
