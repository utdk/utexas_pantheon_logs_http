---
name: Release
about: Prepare code for a new release
labels: 'release'

---

## Pre-release checks

- [ ] Review the [documentation issues](https://github.austin.utexas.edu/eis1-wcs/utdk_docs/issues) for any pending tasks that relate to the issues resolved; if any have not been completed, put this issue on hold & resolve those documentation tasks
- [ ] Contributed module dependencies have been updated, if updates are available
    - (script available at [utdk3_release_packaging](https://github.austin.utexas.edu/eis1-wcs/utdk3_release_packaging/blob/main/releases/utdk_contrib_updater.sh))

```
git clone git@github.austin.utexas.edu:eis1-wcs/utexas_pantheon_logs_http.git
cd utexas_pantheon_logs_http
composer config repositories.drupal composer https://packages.drupal.org/8
composer install
composer outdated --direct
```

## Release pull request tasks

- [ ] Create release branch from develop, e.g. `release/1.0.0`
- [ ] Bump version number in `utexas_pantheon_logs_http.info.yml`
- [ ] Open PR for release branch

## Release completion tasks

- [ ] After approval, merge release branch to develop & master:
- Merge using the Gitflow strategy:

```
git fetch && git checkout develop && git pull origin develop && git merge --no-ff release/3.0.0
git fetch && git checkout master && git pull origin master && git merge --no-ff release/3.0.0
git tag 3.0.0
git push origin develop && git push origin master && git push origin git tag 3.0.0
```

- [ ] [Create a new release](https://github.austin.utexas.edu/eis1-wcs/utexas_pantheon_logs_http/releases/new) (version number and release title should be the same (e.g., `1.0.0`)
- [ ] Use [gren](https://github.com/github-tools/github-release-notes) generate the release notes `gren release --api-url=https://github.austin.utexas.edu/api/v3 --repo=utexas_pantheon_logs_http --username=eis1-wcs --ignore-issues-with="wontfix,release,duplicate,invalid" --override`
