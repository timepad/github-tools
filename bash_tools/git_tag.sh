#!/bin/bash
#on agent should be
# preinstalled gh
# added github token to ~/.config/gh/token

version=$(git describe --abbrev=0 --tags)
release_name=%system.release_name%
release_text=%system.release_text%

branch=$(git rev-parse --abbrev-ref HEAD)
repo_full_name=$(git config --get remote.origin.url | sed 's/.*:\/\/github.com\///;s/.git$//')

repo_name_l=(${repo_full_name//:/ })
repo_name=${repo_name_l[1]}

function generate_new_tagname(){
  version_bits=(${version//./ })
  first_bit=${version_bits[0]}
  last_bit=${version_bits[1]}
  new_last_bit=$((last_bit+1))
  new_tag="$first_bit.$new_last_bit"
}

function tag_existing() {
  git_commit=$(git rev-parse HEAD)
  echo $git_commit
  exist=$(git describe --contains $git_commit)
}


function generate_release() {
  echo "generate_release"
  gh auth login --with-token < ~/.config/gh/token
  echo "  gh api --method POST \
  -H \"Accept: application/vnd.github.v3+json\" /repos/$repo_name/releases \
  -f tag_name=$new_tag \
  -f target_commitish=$branch \
  -f name=\"$release_name\" \
  -f body=\"$release_text\""
  gh api --method POST \
  -H "Accept: application/vnd.github.v3+json" /repos/$repo_name/releases \
  -f tag_name=$new_tag \
  -f target_commitish=$branch \
  -f name="$release_name" \
  -f body="$release_text" || true
}

echo "Check if $version is existing"
tag_existing

if [[ "$exist" = "version" ]];
then
  echo "Tag already exists"
  echo "##teamcity[setParameter name='system.release_tag' value='$version']"
else
   generate_new_tagname
   generate_release
   echo "##teamcity[setParameter name='system.release_tag' value='$new_tag']"
fi