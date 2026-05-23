## Summary

<!--
One or two sentences: what changes and why. Link related issues with `Closes #N` or `Fixes #N`.
-->

## Changes

<!--
- Bullet list of meaningful changes (skip trivial reformatting).
- Note new endpoints, console commands, config keys, or breaking changes here.
-->

## Test plan

<!--
How did you verify the change? Tick what applies:

- [ ] `vendor/bin/pest` — green with Spatie installed
- [ ] `vendor/bin/pest` — green without Spatie (composer remove --dev spatie/laravel-permission && rerun)
- [ ] `composer validate --strict` — clean
- [ ] Added new tests covering the change
- [ ] Manual scenario in a sandbox Laravel app (describe)
-->

## Breaking changes

<!--
List any changes to public PHP API, REST contract, migrations, or config keys.
Leave empty if there are none.
-->

## Checklist

- [ ] Diff is focused; unrelated changes split into other PRs
- [ ] CHANGELOG `Unreleased` section updated if user-visible
- [ ] PHPdoc on new public methods + JsonResources
- [ ] If touching the Spatie gateway: tested both `access.spatie.enabled` true and false
- [ ] If touching the audit listener: confirmed `access.audit.enabled` true and false paths
