# Use the official PHP image as the base image
FROM php:8.1-cli

# Set the working directory
WORKDIR /var/www/html

# Copy the current directory contents into the container at /var/www/html
COPY . /var/www/html

# Install any needed PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Expose port 80
EXPOSE 80

# Run the PHP built-in web server
CMD [ "php", "-S", "0.0.0.0:80", "-t", "." ]
