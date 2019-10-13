# Use "set +e" to ensure the script
# runs the cleanup steps even if dusk tests fail
set +e

# Setup dusk tests if used in this project
if [ -d "tests/Browser" ]; then
  # Update the chrome driver version!
  php artisan dusk:chrome-driver

  # Listen on port 8000 for web requests
  # Use this instead of "php artisan serve"
  php -S 0.0.0.0:8000 -t public 2>/dev/null &
  HTTP_PID=$!
fi

touch database/database.sqlite
./vendor/bin/phpunit
php artisan dusk

TEST_RESULT=$?

kill $HTTP_PID

exit $TEST_RESULT
