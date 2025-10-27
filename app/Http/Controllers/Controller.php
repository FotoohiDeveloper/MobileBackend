<?php

namespace App\Http\Controllers;


/**
 * @OA\Info(title="MyHapaDocument", version="1.2")
 *
 *   @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your Bearer token in the format: Bearer {token}"
 * )

 */
abstract class Controller {}
