<?php

/**
 * Adaptive Garbage Collection Manager for Compucorp Extensions
 *
 * Implements industry best practices for memory management:
 * - Conservative starting intervals (1000 iterations)
 * - Adaptive adjustment based on effectiveness
 * - Multiple trigger types (iteration, memory-threshold, event-based)
 * - Effectiveness monitoring and logging
 */
class CRM_Civicase_Common_GCManager {

  private static $gcStats = [
    'calls' => 0,
    'effective_calls' => 0,
    'last_effective_call' => 0,
  /**
 *Start conservative
 */
    'interval' => 1000,
    'memory_threshold' => 0,
  ];

  private static $initialized = FALSE;

  /**
   * Initialize GC manager with safe defaults
   */
  public static function init() {
    if (!self::$initialized) {
      if (function_exists('gc_enable')) {
        // Enable GC at start
        gc_enable();
      }

      // Set memory threshold to 75% of memory limit or 200MB minimum
      $memoryLimit = self::parseMemoryLimit(ini_get('memory_limit'));
      self::$gcStats['memory_threshold'] = max(
        $memoryLimit * 0.75,
      // 200MB minimum
        200 * 1024 * 1024
      );

      self::$initialized = TRUE;
    }
  }

  /**
   * Intelligently decide whether to collect garbage
   *
   * @param string $operationType Type of operation for tracking
   * @return bool TRUE if GC was performed
   */
  public static function maybeCollectGarbage($operationType = 'default') {
    self::init();

    static $counters = [];
    if (!isset($counters[$operationType])) {
      $counters[$operationType] = 0;
    }

    $counters[$operationType]++;

    // Multiple trigger conditions
    $shouldCollect = FALSE;
    $reason = '';

    // 1. Iteration-count trigger (adaptive)
    if ($counters[$operationType] >= self::$gcStats['interval']) {
      $shouldCollect = TRUE;
      $reason = 'iteration_count';
      $counters[$operationType] = 0;
    }

    // 2. Memory-threshold trigger (always check)
    $currentMemory = memory_get_usage(TRUE);
    if ($currentMemory > self::$gcStats['memory_threshold']) {
      $shouldCollect = TRUE;
      $reason = 'memory_threshold';
      // Reset counter after memory-based collection
      $counters[$operationType] = 0;
    }

    if ($shouldCollect && function_exists('gc_collect_cycles')) {
      $beforeMemory = memory_get_usage(TRUE);
      $startTime = microtime(TRUE);

      $cycles = gc_collect_cycles();

      // ms
      $duration = (microtime(TRUE) - $startTime) * 1000;
      $afterMemory = memory_get_usage(TRUE);
      $memoryFreed = $beforeMemory - $afterMemory;

      self::$gcStats['calls']++;

      if ($cycles > 0) {
        self::$gcStats['effective_calls']++;
        self::$gcStats['last_effective_call'] = self::$gcStats['calls'];
      }

      // Adaptive interval adjustment
      self::adjustInterval($cycles, $reason);

      // Log significant collections only
      // 5MB
      if ($cycles > 0 || $memoryFreed > (5 * 1024 * 1024)) {
        \Civi::log()->debug(sprintf(
          'GC (%s): %d cycles, %.2fms, %s freed, memory: %s, interval: %d',
          $reason,
          $cycles,
          $duration,
          self::formatBytes($memoryFreed),
          self::formatBytes($afterMemory),
          self::$gcStats['interval']
        ));
      }

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Adjust GC interval based on effectiveness
   */
  private static function adjustInterval($cycles, $reason) {
    $callsSinceEffective = self::$gcStats['calls'] - self::$gcStats['last_effective_call'];

    // If GC returned 0 repeatedly, increase interval (less frequent calls)
    if ($cycles === 0 && $callsSinceEffective >= 3) {
      self::$gcStats['interval'] = min(self::$gcStats['interval'] * 1.5, 5000);
    }

    // If GC was effective, maintain or slightly reduce interval
    if ($cycles > 0) {
      // Many cycles collected - memory pressure exists
      if ($cycles > 10) {
        self::$gcStats['interval'] = max(self::$gcStats['interval'] * 0.8, 500);
      }
    }

    // Memory-threshold triggered collections don't affect iteration interval
    if ($reason === 'memory_threshold') {
      // Keep iteration interval stable, memory threshold is the primary control
    }
  }

  /**
   * Parse PHP memory limit string to bytes
   */
  private static function parseMemoryLimit($limit) {
    if ($limit === '-1') {
      return PHP_INT_MAX;
    }

    $value = (int) $limit;
    $unit = strtolower(substr($limit, -1));

    switch ($unit) {
      case 'g':
        return $value * 1024 * 1024 * 1024;

      case 'm':
        return $value * 1024 * 1024;

      case 'k':
        return $value * 1024;

      default:
        return $value;
    }
  }

  /**
   * Format bytes for human-readable logging
   */
  private static function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
      return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
      return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
      return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
  }

  /**
   * Get GC statistics for monitoring
   */
  public static function getStats() {
    return self::$gcStats;
  }

}
