<?php
/*
 * Doctor consultation room view — deliberately identical in structure
 * to the patient consultation room view.  We keep both as independent
 * PHP files (rather than a shared require) so each role can safely be
 * customised later in Week 7 without leaking details across roles.
 */
require __DIR__ . '/../../patient/consultations/room.php';
