# Management Record Detail and Revision Safety

Extended in v0.6.0-dev.

## Detail routes

Developer:

- /developer/manage/company/{id}
- /developer/manage/project/{id}
- /developer/manage/property/{id}
- /developer/manage/listing/{id}
- /developer/manage/inquiry/{id}
- /developer/manage/lead/{id}

Admin uses the same canonical record view under:

- /dashboard/manage/{resource}/{id}

Developer record reads are always resolved through active company membership. A resource outside the user's company scope returns no record.

## Edit safety

Company, project, property, and listing content is editable by company users only while the record is:

- draft
- rejected

Reviewed or published records are locked.

This closes the former paused-listing loophole where a developer could change reviewed content while paused and then self-resume publication without another review.

Media uses the same rule. Uploading, removing, or changing a primary image is allowed only while the target record is draft or rejected.

## Record experience

The shared record page provides:

- record metadata
- current status
- allowed lifecycle actions
- workflow history and actor attribution
- edit forms for editable records
- media attachment metadata
- primary-image control
- media removal
- upload controls for editable records

The table view links into these record pages instead of embedding duplicate detail implementations.

## Media lifecycle

MediaStorageService and MediaManagementService both enforce company ownership and editable-state checks.

Setting a primary image clears the previous primary item in the same entity collection.

Removing the last attachment for an asset deletes the database asset record and then removes the private file when filesystem cleanup succeeds.

## QA

The domain smoke test verifies:

- draft media is not publicly accessible
- primary-media management works while draft
- reviewed listing/company/media edits are blocked
- scoped developer record detail cannot read another company's listing
- admin record detail can inspect platform inventory
- public media becomes available only after the attached listing is published
