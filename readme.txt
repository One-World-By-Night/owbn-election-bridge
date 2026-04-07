# OWBN Election Bridge

Manages coordinator elections for One World by Night. Bridges candidate applications with the voting system.

Version: 1.0.0
Deployed to: council.owbn.net

## What It Does

OWBN runs annual coordinator elections with 25+ positions. This plugin handles the full lifecycle: candidates apply via a public form (no login required), admins approve applications, and approved candidates are automatically added to votes managed by wp-voting-plugin.

Key features:

- Guest applications -- public form with reCAPTCHA v3, no login required. Fields: name, email, approving group, position, language, content.
- Application moderation -- standard WordPress pending/publish workflow for admin approval
- Vote integration -- approved candidates automatically added as voting options
- FPTP and RCV -- supports both voting types. FPTP auto-switches to RCV when candidate count warrants it (draft votes only, one-way switch).
- Abstain + Reject All -- every vote is seeded with both options automatically, always present
- Candidate withdrawal -- candidates can be removed during open votes, with logging
- Ballot view page -- card-based UI showing all active elections (in progress)

## Dependencies

- wp-voting-plugin (provides the voting engine)
- owbn-core (provides accessSchema for vote permissions)

## Requirements

- WordPress 5.0+, PHP 7.4+

## License

GPL-2.0-or-later
