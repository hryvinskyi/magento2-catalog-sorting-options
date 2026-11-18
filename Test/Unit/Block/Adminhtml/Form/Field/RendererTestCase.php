<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\CatalogSortingOptions\Test\Unit\Block\Adminhtml\Form\Field;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager as AppObjectManager;
use Magento\Framework\Escaper;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\View\Element\Context;
use Magento\Framework\ZendEscaper;
use PHPUnit\Framework\TestCase;

/**
 * Base of the tests that render the select blocks of the sort options field
 *
 * Rendering a block escapes through the shared object manager and dispatches its render events; this puts a
 * working escaper and a silent event manager in place, and puts the object manager back afterwards.
 */
abstract class RendererTestCase extends TestCase
{
    /**
     * @var mixed Object manager instance in place before this test, restored afterwards
     */
    private mixed $previousObjectManager;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $instance = new \ReflectionProperty(AppObjectManager::class, '_instance');
        $this->previousObjectManager = $instance->getValue();

        $translateInline = $this->createMock(InlineInterface::class);
        $translateInline->method('isAllowed')->willReturn(false);
        (new ObjectManagerHelper($this))->prepareObjectManager([
            [InlineInterface::class, $translateInline],
            [ZendEscaper::class, new ZendEscaper()],
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        (new \ReflectionProperty(AppObjectManager::class, '_instance'))->setValue(null, $this->previousObjectManager);
    }

    /**
     * Build the context a select block renders with
     *
     * @return Context
     */
    protected function rendererContext(): Context
    {
        $context = $this->createMock(Context::class);
        $context->method('getEscaper')->willReturn(new Escaper());
        $context->method('getEventManager')->willReturn($this->createMock(ManagerInterface::class));
        $context->method('getScopeConfig')->willReturn($this->createMock(ScopeConfigInterface::class));

        return $context;
    }
}
