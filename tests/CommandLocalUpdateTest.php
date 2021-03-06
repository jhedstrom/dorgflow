<?php

namespace Dorgflow\Tests;

use Symfony\Component\DependencyInjection\Reference;

/**
 * System test for the local update command.
 *
 * This mocks raw input, that is, git info, git branches, and drupal.org data.
 *
 * Run with:
 * @code
 *   vendor/bin/phpunit tests/CommandLocalUpdateTest.php
 * @endcode
 */
class CommandLocalUpdateTest extends CommandTestBase {

  /**
   * The feature branch name to use in mocked data.
   */
  const FEATURE_BRANCH_NAME = '123456-terrible-bug';

  /**
   * The feature branch tip sha to use in mocked data.
   */
  const FEATURE_BRANCH_SHA = 'sha-feature';

  /**
   * The issue number to use in mocked data.
   */
  const ISSUE_NUMBER = '123456';

  /**
   * Test the command bails when git is not clean.
   */
  public function testGitUnclean() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    $git_info = $this->createMock(\Dorgflow\Service\GitInfo::class);
    $git_info->method('gitIsClean')
      ->willReturn(FALSE);

    $container->set('git.info', $git_info);
    // These won't get called, so don't need to mock anything.
    $container->set('waypoint_manager.branches', $this->getMockBuilder(StdClass::class));
    $container->set('waypoint_manager.patches', $this->getMockBuilder(StdClass::class));
    $container->set('git.executor', $this->getMockBuilder(StdClass::class));

    $command_tester = $this->setUpCommandTester($container, 'update', \Dorgflow\Command\LocalUpdate::class);

    $this->expectException(\Exception::class);

    $command_tester->execute([
      'command'  => 'update',
    ]);
  }

  /**
   * Tests the case where the feature branch can't be found.
   */
  public function testNoFeatureBranch() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    $git_info = $this->createMock(\Dorgflow\Service\GitInfo::class);
    // Git is clean so the command proceeds.
    $git_info->method('gitIsClean')
      ->willReturn(TRUE);
    $branch_list = [
      // There is no feature branch.
      '8.x-2.x' => 'sha',
      'some-branch-name' => 'sha',
      'something-else' => 'sha',
    ];
    $git_info->method('getBranchList')
      ->willReturn($branch_list);
    $git_info->method('getBranchListReachable')
      ->willReturn($branch_list);
    $container->set('git.info', $git_info);

    // The analyser returns an issue number.
    $analyser = $this->createMock(\Dorgflow\Service\Analyser::class);
    $analyser->method('deduceIssueNumber')
      ->willReturn(self::ISSUE_NUMBER);
    $container->set('analyser', $analyser);

    // The git executor should not be called at all.
    $git_executor = $this->createMock(\Dorgflow\Service\GitExecutor::class);
    $git_executor->expects($this->never())->method($this->anything());
    $container->set('git.executor', $git_executor);

    // Drupal.org API should not be called at all.
    $drupal_org = $this->createMock(\Dorgflow\Service\DrupalOrg::class);
    $drupal_org->expects($this->never())->method($this->anything());
    $container->set('drupal_org', $drupal_org);

    // Need the real service for this, as we want the command to get the branch
    // object from it, based on the mocked git.info service.
    $container
      ->register('waypoint_manager.branches', \Dorgflow\Service\WaypointManagerBranches::class)
      ->addArgument(new Reference('git.info'))
      ->addArgument(new Reference('drupal_org'))
      ->addArgument(new Reference('git.executor'))
      ->addArgument(new Reference('analyser'));

    $container->set('waypoint_manager.patches', $this->getMockBuilder(\Dorgflow\Service\WaypointManagerPatches::class));

    $command_tester = $this->setUpCommandTester($container, 'update', \Dorgflow\Command\LocalUpdate::class);

    $this->expectException(\Exception::class);

    $command_tester->execute([
      'command'  => 'update',
    ]);
  }

  /**
   * Tests the case where the feature branch isn't current.
   */
  public function testNotOnFeatureBranch() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    $git_info = $this->createMock(\Dorgflow\Service\GitInfo::class);
    // Git is clean so the command proceeds.
    $git_info->method('gitIsClean')
      ->willReturn(TRUE);
    $branch_list = [
      // There is a feature branch.
      self::FEATURE_BRANCH_NAME => self::FEATURE_BRANCH_SHA,
      '8.x-2.x' => 'sha',
      'some-branch-name' => 'sha',
      'something-else' => 'sha',
    ];
    $git_info->method('getBranchList')
      ->willReturn($branch_list);
    $git_info->method('getBranchListReachable')
      ->willReturn($branch_list);
    // Master branch is current rather than the feature branch.
    $git_info->method('getCurrentBranch')
      ->willReturn('8.x-2.x');
    $container->set('git.info', $git_info);

    // The analyser returns an issue number.
    $analyser = $this->createMock(\Dorgflow\Service\Analyser::class);
    $analyser->method('deduceIssueNumber')
      ->willReturn(self::ISSUE_NUMBER);
    $container->set('analyser', $analyser);

    // The git executor should not be called at all.
    $git_executor = $this->createMock(\Dorgflow\Service\GitExecutor::class);
    $git_executor->expects($this->never())->method($this->anything());
    $container->set('git.executor', $git_executor);

    // Drupal.org API should not be called at all.
    $drupal_org = $this->createMock(\Dorgflow\Service\DrupalOrg::class);
    $drupal_org->expects($this->never())->method($this->anything());
    $container->set('drupal_org', $drupal_org);

    // Need the real service for this, as we want the command to get the branch
    // object from it, based on the mocked git.info service.
    $container
      ->register('waypoint_manager.branches', \Dorgflow\Service\WaypointManagerBranches::class)
      ->addArgument(new Reference('git.info'))
      ->addArgument(new Reference('drupal_org'))
      ->addArgument(new Reference('git.executor'))
      ->addArgument(new Reference('analyser'));

    $container->set('waypoint_manager.patches', $this->getMockBuilder(\Dorgflow\Service\WaypointManagerPatches::class));

    $command_tester = $this->setUpCommandTester($container, 'update', \Dorgflow\Command\LocalUpdate::class);

    $this->expectException(\Exception::class);

    $command_tester->execute([
      'command'  => 'update',
    ]);
  }

  /**
   * Tests the case the feature branch has nothing and there are new patches.
   */
  public function testEmptyFeatureBranchNewPatches() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    $git_info = $this->createMock(\Dorgflow\Service\GitInfo::class);
    // Git is clean so the command proceeds.
    $git_info->method('gitIsClean')
      ->willReturn(TRUE);
    $branch_list = [
      // There is a feature branch, and its SHA is the same as the master
      // branch.
      self::FEATURE_BRANCH_NAME => 'sha-master',
      '8.3.x' => 'sha-master',
      'some-branch-name' => 'sha',
      'something-else' => 'sha',
    ];
    $git_info->method('getBranchList')
      ->willReturn($branch_list);
    $git_info->method('getBranchListReachable')
      ->willReturn($branch_list);
    // Feature branch is current.
    $git_info->method('getCurrentBranch')
      ->willReturn(self::FEATURE_BRANCH_NAME);
    $container->set('git.info', $git_info);

    $git_log = $this->createMock(\Dorgflow\Service\GitLog::class);
    // Feature branch log is empty.
    $git_log->method('getFeatureBranchLog')
      ->willReturn([]);
    $container->set('git.log', $git_log);

    // The analyser returns an issue number.
    $analyser = $this->createMock(\Dorgflow\Service\Analyser::class);
    $analyser->method('deduceIssueNumber')
      ->willReturn(self::ISSUE_NUMBER);
    $container->set('analyser', $analyser);

    $drupal_org = $this->createMock(\Dorgflow\Service\DrupalOrg::class);
    $drupal_org->method('getIssueNodeTitle')
      ->willReturn('Terribly awful bug');
    $patch_file_data = [
      0 => [
        'fid' => 200,
        'cid' => 400,
        'index' => 1,
        'filename' => 'fix-1.patch',
        'display' => TRUE,
        'applies' => TRUE,
        'expected' => 'apply',
      ],
      // Not displayed; will be skipped.
      1 => [
        'fid' => 205,
        'cid' => 405,
        'index' => 5,
        'filename' => 'fix-5.patch',
        'display' => FALSE,
        'expected' => 'skip',
      ],
      // Not a patch; will be skipped.
      2 => [
        'fid' => 206,
        'cid' => 406,
        'index' => 6,
        'filename' => 'fix-5.not.patch.txt',
        'display' => TRUE,
        'expected' => 'skip',
      ],
      3 => [
        'fid' => 210,
        'cid' => 410,
        'index' => 10,
        'filename' => 'fix-10.patch',
        'display' => TRUE,
        'applies' => TRUE,
        'expected' => 'apply',
      ],
    ];
    $this->setUpDrupalOrgExpectations($drupal_org, $patch_file_data);
    $container->set('drupal_org', $drupal_org);

    $container->set('commit_message', $this->createMock(\Dorgflow\Service\CommitMessageHandler::class));

    $git_executor = $this->createMock(\Dorgflow\Service\GitExecutor::class);
    // No new branches will be created.
    $git_executor->expects($this->never())
      ->method('createNewBranch');

    // Both patches will be applied.
    $this->setUpGitExecutorPatchExpectations($git_executor, $patch_file_data);
    $container->set('git.executor', $git_executor);

    // Add real versions of any remaining services not yet registered.
    $this->completeServiceContainer($container);

    $command_tester = $this->setUpCommandTester($container, 'update', \Dorgflow\Command\LocalUpdate::class);

    $command_tester->execute([
      'command'  => 'update',
    ]);
  }

  /**
   * Tests a feature branch that has patches, with new patches on the issue.
   */
  public function testFeatureBranchFurtherPatches() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    $git_info = $this->getGitInfoCleanWithFeatureBranch();
    $container->set('git.info', $git_info);

    $git_log = $this->createMock(\Dorgflow\Service\GitLog::class);
    // Feature branch log: two patches have previously been committed.
    $git_log->method('getFeatureBranchLog')
      ->willReturn([
        'sha-patch-1' => [
          'sha' => 'sha-patch-1',
          'message' => "Patch from Drupal.org. Comment: 1; URL: http://url.com/1234; file: applied.patch; fid: 11. Automatic commit by dorgflow.",
        ],
        self::FEATURE_BRANCH_SHA => [
          'sha' => self::FEATURE_BRANCH_SHA,
          'message' => "Patch from Drupal.org. Comment: 2; URL: http://url.com/1234; file: applied.patch; fid: 12. Automatic commit by dorgflow.",
        ],
      ]);
    $container->set('git.log', $git_log);

    $drupal_org = $this->createMock(\Dorgflow\Service\DrupalOrg::class);
    $drupal_org->method('getIssueNodeTitle')
      ->willReturn('Terribly awful bug');
    $patch_file_data = [
      0 => [
        // Patch has previously been applied.
        'fid' => 11,
        'cid' => 21,
        'index' => 1,
        'filename' => 'patch-1.patch',
        'display' => TRUE,
        // We expect that this will not be attempted again.
        'expected' => 'skip',
      ],
      1 => [
        // Patch has previously been applied.
        'fid' => 12,
        'cid' => 22,
        'index' => 2,
        'filename' => 'patch-2.patch',
        'display' => TRUE,
        // We expect that this will not be attempted again.
        'expected' => 'skip',
      ],
      2 => [
        // New patch.
        'fid' => 13,
        'cid' => 23,
        'index' => 3,
        'filename' => 'new.patch',
        'display' => TRUE,
        'applies' => TRUE,
        // The new patch will be attempted to be applied.
        'expected' => 'apply',
      ],
    ];
    $this->setUpDrupalOrgExpectations($drupal_org, $patch_file_data);
    $container->set('drupal_org', $drupal_org);

    // The analyser returns an issue number.
    $analyser = $this->createMock(\Dorgflow\Service\Analyser::class);
    $analyser->method('deduceIssueNumber')
      ->willReturn(self::ISSUE_NUMBER);
    $container->set('analyser', $analyser);

    $git_executor = $this->createMock(\Dorgflow\Service\GitExecutor::class);
    // No new branches will be created.
    $git_executor->expects($this->never())
      ->method('createNewBranch');
    // Only the new patch file will be applied.
    $this->setUpGitExecutorPatchExpectations($git_executor, $patch_file_data);
    $container->set('git.executor', $git_executor);

    // Add real versions of any remaining services not yet registered.
    $this->completeServiceContainer($container);

    $command_tester = $this->setUpCommandTester($container, 'update', \Dorgflow\Command\LocalUpdate::class);

    $command_tester->execute([
      'command'  => 'update',
    ]);
  }

  /**
   * Tests a prior patch that failed to apply is not applied again.
   */
  public function testFeatureBranchFailingPatch() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    $git_info = $this->createMock(\Dorgflow\Service\GitInfo::class);
    // Git is clean so the command proceeds.
    $git_info->method('gitIsClean')
      ->willReturn(TRUE);
    $branch_list = [
      // There is a feature branch.
      self::FEATURE_BRANCH_NAME => self::FEATURE_BRANCH_SHA,
      '8.3.x' => 'sha-master',
      'some-branch-name' => 'sha',
      'something-else' => 'sha',
    ];
    $git_info->method('getBranchList')
      ->willReturn($branch_list);
    $git_info->method('getBranchListReachable')
      ->willReturn($branch_list);
    // Feature branch is current.
    $git_info->method('getCurrentBranch')
      ->willReturn(self::FEATURE_BRANCH_NAME);
    $container->set('git.info', $git_info);

    $git_log = $this->createMock(\Dorgflow\Service\GitLog::class);
    // Feature branch log has a commit for the second patch on the issue, since
    // the first one failed.
    $git_log->method('getFeatureBranchLog')
      ->willReturn([
        self::FEATURE_BRANCH_SHA => [
          'sha' => self::FEATURE_BRANCH_SHA,
          'message' => "Patch from Drupal.org. Comment: 10; URL: http://url.com/1234; file: applied.patch; fid: 210. Automatic commit by dorgflow.",
        ],
      ]);
    $container->set('git.log', $git_log);

    // The analyser returns an issue number.
    $analyser = $this->createMock(\Dorgflow\Service\Analyser::class);
    $analyser->method('deduceIssueNumber')
      ->willReturn(self::ISSUE_NUMBER);
    $container->set('analyser', $analyser);

    $drupal_org = $this->createMock(\Dorgflow\Service\DrupalOrg::class);
    $drupal_org->method('getIssueNodeTitle')
      ->willReturn('Terribly awful bug');
    $patch_file_data = [
      0 => [
        // A patch that previously failed to apply.
        'fid' => 200,
        'cid' => 400,
        'index' => 1,
        'filename' => 'failing.patch',
        'display' => TRUE,
        // We expect that this will not be attempted again.
        'expected' => 'skip',
      ],
      1 => [
        // Patch has previously been applied.
        // This is the condition for the prior failing patch to not be attempted
        // again.
        'fid' => 210,
        'cid' => 410,
        'index' => 10,
        'filename' => 'applied.patch',
        'display' => TRUE,
        'expected' => 'skip',
      ],
      2 => [
        // New patch.
        'fid' => 220,
        'cid' => 420,
        'index' => 20,
        'filename' => 'new.patch',
        'display' => TRUE,
        'applies' => TRUE,
        'expected' => 'apply',
      ],
    ];
    $this->setUpDrupalOrgExpectations($drupal_org, $patch_file_data);
    $container->set('drupal_org', $drupal_org);

    $git_executor = $this->createMock(\Dorgflow\Service\GitExecutor::class);
    // No new branches will be created.
    $git_executor->expects($this->never())
      ->method('createNewBranch');
    // Only the new patch file will be applied.
    $this->setUpGitExecutorPatchExpectations($git_executor, $patch_file_data);
    $container->set('git.executor', $git_executor);

    // Add real versions of any remaining services not yet registered.
    $this->completeServiceContainer($container);

    $command_tester = $this->setUpCommandTester($container, 'update', \Dorgflow\Command\LocalUpdate::class);

    $command_tester->execute([
      'command'  => 'update',
    ]);
  }

  /**
   * Tests a feature branch ending in a local patch, new patches on the issue.
   *
   * The scenario is as follows:
   *  - user set up the issue with a patch from d.org
   *  - user created a patch and uploaded it
   *  - another user posted a patch to d.org
   *  - the user is now updating.
   */
  public function testFeatureBranchLocalPatchHeadFurtherPatches() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    // Create a standard git.info service for the command to proceed.
    $git_info = $this->getGitInfoCleanWithFeatureBranch();
    $container->set('git.info', $git_info);

    $git_log = $this->createMock(\Dorgflow\Service\GitLog::class);
    // Feature branch log: one d.org patch, a work commit, then one local patch.
    $git_log->method('getFeatureBranchLog')
      ->willReturn([
        'sha-patch-1' => [
          'sha' => 'sha-patch-1',
          'message' => "Patch from Drupal.org. Comment: 21; URL: http://url.com/1234; file: patch-1.patch; fid: 11. Automatic commit by dorgflow.",
        ],
        'sha-work' => [
          'sha' => 'sha-work',
          'message' => "Fixing the bug.",
        ],
        self::FEATURE_BRANCH_SHA => [
          // This is the tip of the feature branch.
          'sha' => self::FEATURE_BRANCH_SHA,
          'message' => "Patch for Drupal.org. Comment (expected): 22; file: 123456-22.project.bug-description.patch. Automatic commit by dorgflow.",
        ],
      ]);
    $container->set('git.log', $git_log);

    $drupal_org = $this->createMock(\Dorgflow\Service\DrupalOrg::class);
    $drupal_org->method('getIssueNodeTitle')
      ->willReturn('Terribly awful bug');
    $patch_file_data = [
      0 => [
        // Patch has previously been applied.
        'fid' => 11,
        'cid' => 21,
        'index' => 1,
        'filename' => 'patch-1.patch',
        'display' => TRUE,
        // We expect that this will not be attempted again.
        'expected' => 'skip',
      ],
      1 => [
        // Patch came from us.
        'fid' => 12,
        'cid' => 22,
        'index' => 2,
        'filename' => '123456-22.project.bug-description.patch',
        'display' => TRUE,
        // We expect that this will not be attempted again.
        'expected' => 'skip',
      ],
      2 => [
        // New patch.
        'fid' => 13,
        'cid' => 23,
        'index' => 3,
        'filename' => 'patch-23.patch',
        'display' => TRUE,
        'applies' => TRUE,
        // The new patch will be attempted to be applied.
        'expected' => 'apply',
      ],
    ];
    $this->setUpDrupalOrgExpectations($drupal_org, $patch_file_data);
    $container->set('drupal_org', $drupal_org);

    // The analyser returns an issue number.
    $analyser = $this->createMock(\Dorgflow\Service\Analyser::class);
    $analyser->method('deduceIssueNumber')
      ->willReturn(self::ISSUE_NUMBER);
    $container->set('analyser', $analyser);

    $git_executor = $this->createMock(\Dorgflow\Service\GitExecutor::class);
    // No new branches will be created.
    $git_executor->expects($this->never())
      ->method('createNewBranch');
    // Only the new patch file will be applied.
    $this->setUpGitExecutorPatchExpectations($git_executor, $patch_file_data);
    $container->set('git.executor', $git_executor);

    // Add real versions of any remaining services not yet registered.
    $this->completeServiceContainer($container);

    $command_tester = $this->setUpCommandTester($container, 'update', \Dorgflow\Command\LocalUpdate::class);

    $command_tester->execute([
      'command'  => 'update',
    ]);
  }

  /**
   * Creates a git info service with typical data for the command to proceed.
   *
   * - git is clean
   * - the feature branch exists, is current, and is ahead of the master branch.
   *
   * @return
   *  The mocked git.info object.
   */
  protected function getGitInfoCleanWithFeatureBranch() {
    $git_info = $this->createMock(\Dorgflow\Service\GitInfo::class);
    // Git is clean so the command proceeds.
    $git_info->method('gitIsClean')
      ->willReturn(TRUE);
    $branch_list = [
      // There is a feature branch, which is further ahead than the master
      // branch.
      self::FEATURE_BRANCH_NAME => self::FEATURE_BRANCH_SHA,
      '8.3.x' => 'sha-master',
      'some-branch-name' => 'sha',
      'something-else' => 'sha',
    ];
    $git_info->method('getBranchList')
      ->willReturn($branch_list);
    $git_info->method('getBranchListReachable')
      ->willReturn($branch_list);
    // Feature branch is current.
    $git_info->method('getCurrentBranch')
      ->willReturn(self::FEATURE_BRANCH_NAME);

    return $git_info;
  }

}
