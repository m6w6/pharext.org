<?php

namespace app\Controller\Github;

use app\Controller\Github;

class Repo extends Github
{
	function __invoke(array $args = null) {
		extract($args);
		if ($this->checkToken()) {
			try {
				$this->github->fetchRepo(
					"$owner/$name",
					[$this, "repoCallback"]
				)->send();
			} catch (\app\Github\Exception $exception) {
				$this->app->getView()->addData(compact("exception", "owner", "name"));
			}
			$this->app->display("github/repo");
		}
	}

	function repoCallback($repo, $links) {
		$this->app->getView()->addData(compact("repo") + [
			"title" => "Github: {$repo->name}"
		]);
		settype($repo->tags, "object");
		$this->github->fetchHooks($repo->full_name, function($hooks) use($repo) {
			$repo->hooks = $hooks;
		});
		$this->github->fetchTags($repo->full_name, 1, $this->createTagsCallback($repo));
		$this->github->fetchReleases($repo->full_name, 1, $this->createReleasesCallback($repo));
		$this->github->fetchContents($repo->full_name, null, $this->createContentsCallback($repo));
	}

	function createReleasesCallback($repo) {
		return function($releases, $links) use($repo) {
			foreach ($releases as $release) {
				$tag = $release->tag_name;
				settype($repo->tags->$tag, "object");
				$repo->tags->$tag->release = $release;
			}
		};
	}

	function createTagsCallback($repo) {
		return function($tags, $links) use ($repo) {
			foreach ($tags as $tag) {
				$name = $tag->name;
				settype($repo->tags->$name, "object");
				$repo->tags->$name->tag = $tag;
			}
		};
	}
	
	function createContentsCallback($repo) {
		return function($tree) use($repo) {
			foreach ($tree as $entry) {
				if ($entry->type !== "file" || $entry->size <= 0) {
					continue;
				}
				if ($entry->name === "config.m4" || fnmatch("config?.m4", $entry->name)) {
					$repo->config_m4 = $entry->name;
				} elseif ($entry->name === "package.xml" || $entry->name === "package2.xml") {
					$repo->package_xml = $entry->name;
				} elseif ($entry->name === "pharext_package.php") {
					$repo->pharext_package_php = $entry->name;
				}
			}
		};
	}
}
