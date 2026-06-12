<?php

/**
 * Data plane (/v1/apis/{apiId}/data/...) integration tests.
 *
 * Provisions a throwaway API via the Management API before running tests.
 *
 * @see docs/data_plane_test_plan.md
 */
class TestDataPlaneAPI extends DataPlaneTestCase
{
    use DataPlaneTestsTrait;
}
