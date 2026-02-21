echo "Pulling latest changes from main..."
git pull origin main

echo "Running composer install..."
composer install --no-interaction --prefer-dist

echo "Running composer upgrade..."
composer upgrade --no-interaction --prefer-dist

if [[ -n $(git status -s) ]]; then
    echo "There are changes in the repository."

    git add .

    echo "Enter commit message: "
    read commit_message
    git commit -m "$commit_message"

    echo "Pushing changes to main..."
    git push origin main
else
    echo "No changes to commit."
fi