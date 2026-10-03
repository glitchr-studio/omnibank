<?php

namespace Omnibank\Model;

/** Where the user's consent to reach the accounts stands, whatever the provider calls it. */
enum ConsentStatus: string
{
    /** The accounts can be read. */
    case ACTIVE = 'active';
    /** The user must go through the provider's page again (expired consent, SCA, new password). */
    case NEEDS_RENEWAL = 'needs_renewal';
    /** The user or the bank withdrew it. */
    case REVOKED = 'revoked';
    /** Never given. */
    case NONE = 'none';
}
