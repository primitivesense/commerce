<?php

namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Class Market_LineItemService
 *
 * @package Craft
 */
class Market_LineItemService extends BaseApplicationComponent
{
	/**
	 * @param int $id
	 *
	 * @return Market_LineItemModel[]
	 */
	public function getAllByOrderId($id)
	{
		$lineItems = Market_LineItemRecord::model()->findAllByAttributes(['orderId' => $id]);

		return Market_LineItemModel::populateModels($lineItems);
	}

	/**
	 * Find line item by order and variant
	 *
	 * @param int $orderId
	 * @param int $variantId
	 *
	 * @return Market_LineItemModel
	 */
	public function getByOrderVariant($orderId, $variantId)
	{
		$variant = Market_LineItemRecord::model()->findByAttributes([
			'orderId'   => $orderId,
			'variantId' => $variantId,
		]);

		return Market_LineItemModel::populateModel($variant);
	}


	/**
	 * Update line item and recalculate order
	 * @TODO check that the line item belongs to the current user
	 *
	 * @param Market_LineItemModel $lineItem
	 * @param string               $error
	 *
	 * @return bool
	 * @throws Exception
	 */
	public function update(Market_LineItemModel $lineItem, &$error = '')
	{
		if ($this->save($lineItem)) {
			craft()->market_order->save($lineItem->order);
			return true;
		} else {
			$errors = $lineItem->getAllErrors();
			$error  = array_pop($errors);
			return false;
		}
	}

	/**
	 * @param int $id
	 *
	 * @return Market_LineItemModel
	 */
	public function getById($id)
	{
		$lineItem = Market_LineItemRecord::model()->findById($id);

		return Market_LineItemModel::populateModel($lineItem);
	}

	/**
	 * @param Market_LineItemModel $lineItem
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function save(Market_LineItemModel $lineItem)
	{
		if (!$lineItem->id) {
			$lineItemRecord = new Market_LineItemRecord();
		} else {
			$lineItemRecord = Market_LineItemRecord::model()->findById($lineItem->id);

			if (!$lineItemRecord) {
				throw new Exception(Craft::t('No line item exists with the ID “{id}”', ['id' => $lineItem->id]));
			}
		}

		$lineItem->total = (
				$lineItem->price +
				$lineItem->discountAmount +
				$lineItem->taxAmount +
				$lineItem->shippingAmount +
				$lineItem->saleAmount
			) * $lineItem->qty;

		$lineItemRecord->variantId     = $lineItem->variantId;
		$lineItemRecord->orderId       = $lineItem->orderId;
		$lineItemRecord->taxCategoryId = $lineItem->taxCategoryId;

		$lineItemRecord->qty         = $lineItem->qty;
		$lineItemRecord->price       = $lineItem->price;
		$lineItemRecord->total       = $lineItem->total;
		$lineItemRecord->weight      = $lineItem->weight;
		$lineItemRecord->optionsJson = $lineItem->optionsJson;

		$lineItemRecord->saleAmount     = $lineItem->saleAmount;
		$lineItemRecord->taxAmount      = $lineItem->taxAmount;
		$lineItemRecord->discountAmount = $lineItem->discountAmount;
		$lineItemRecord->shippingAmount = $lineItem->shippingAmount;

		$lineItemRecord->validate();
		$lineItem->addErrors($lineItemRecord->getErrors());

		MarketDbHelper::beginStackedTransaction();
		try {
			if (!$lineItem->hasErrors()) {
				$lineItemRecord->save(false);
				$lineItemRecord->id = $lineItem->id;

				MarketDbHelper::commitStackedTransaction();

				return true;
			}
		} catch (\Exception $e) {
			MarketDbHelper::rollbackStackedTransaction();
			throw $e;
		}

		return false;
	}

	/**
	 * @param int $variantId
	 * @param int $orderId
	 * @param int $qty
	 *
	 * @return Market_LineItemModel
	 */
	public function create($variantId, $orderId, $qty)
	{
		$lineItem            = new Market_LineItemModel();
		$lineItem->variantId = $variantId;
		$lineItem->qty       = $qty;
		$lineItem->orderId   = $orderId;

		$variant = craft()->market_variant->getById($variantId);

		if ($variant->id) {
            $lineItem->fillFromVariant($variant);
		} else {
			$lineItem->addError('variantId', 'variant not found');
		}

		return $lineItem;
	}

	/**
	 * @param Market_LineItemModel $lineItem
	 *
	 * @return int
	 */
	public function delete($lineItem)
	{
		return Market_LineItemRecord::model()->deleteByPk($lineItem->id);
	}

	/**
	 * @param int $orderId
	 *
	 * @return int
	 */
	public function deleteAllByOrderId($orderId)
	{
		return Market_LineItemRecord::model()->deleteAllByAttributes(['orderId' => $orderId]);
	}
}