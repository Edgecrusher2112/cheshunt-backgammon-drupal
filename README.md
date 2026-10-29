# Cheshunt Backgammon

Drupal site for keeping track of backgammon leagues and knockout in East Herts. Uses Docker4Drupal for local development.

* Read the docs on [**how to use**](https://wodby.com/docs/stacks/drupal/local#usage)

## League
TODO

## Knockout

TODO

## Workflow Stuff For Me

### Deploy in two stages

#### Stage 1

Locally:

docker compose exec php drush pm:uninstall MODULE NAME -y
drush cex -y
git commit
git push

#### Stage 2

composer remove MODULE NAME
git commit
git push