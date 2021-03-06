<?php

namespace iBrand\EC\Open\Backend\Store\Repositories;

use iBrand\EC\Open\Backend\Store\Model\ElDiscount;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use iBrand\EC\Open\Backend\Store\Model\ElDiscountCoupon;
use DB;

/**
 * Class DiscountConditionRepositoryEloquent
 * @package namespace App\Repositories;
 */
class DiscountCouponRepository extends BaseRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return ElDiscountCoupon::class;
    }


    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    public function getCouponsHistoryPaginated($where, $limit = 50, $time = [])
    {
        $query = $this->model->whereNotNull('used_at')->orderBy('used_at', 'desc');

        if (count($where) > 0) {
            foreach ($where as $key => $value) {
                if ($key != 'order_no' AND $key!='mobile') {
                    if (is_array($value)) {
                        list($operate, $va) = $value;
                        $query = $query->where($key, $operate, $va);
                    } else {
                        $query = $query->where($key, $value);
                    }
                }
            }
        }

        if (count($time) > 0) {
            foreach ($time as $key => $value) {
                if (is_array($value)) {
                    list($operate, $va) = $value;
                    $query = $query->where($key, $operate, $va);
                } else {
                    $query = $query->where($key, $value);
                }
            }
        }

        $query->whereHas('order', function ($query) use ($where) {
            $query->where('origin_type', 'coupon')
                ->where(function ($query) use ($where) {
                    if (isset($where['order_no'])) {
                        list($operate, $va) = $where['order_no'];
                        $query->where('order_no', $operate, $va);
                    }
                });
        });

        $query->whereHas('user', function ($query) use ($where) {
               $query->where(function ($query) use ($where) {
                    if (isset($where['mobile'])) {
                        list($operate, $va) = $where['mobile'];
                        $query->where('el_user.mobile', $operate, $va);
                    }
                });
        });

        $query = $query->with('order')->with('user');

        if ($limit == 0) {
            return $query->all();
        } else {
            return $query->paginate($limit);
        }
    }


    /**
     * 用户获取优惠券
     * @param $user_id
     * @param $coupon_id
     * @return bool
     */
    public function userGetCoupons($user_id, $coupon_id, $type = 0)
    {
        $coupon = new ElDiscountCoupon();
        $input['user_id'] = $user_id;
        $input['discount_id'] = $coupon_id;
        if ($type == 1) {
            $coupon_code = createOfflineCouponCode();
            $coupon_list = ElDiscountCoupon::where(['code' => $coupon_code])->first();
            if (count($coupon_list)) {
                $coupon_code = createOfflineCouponCode();
            }
            $input['code'] = $coupon_code;
            $input['expires_at'] = Carbon::now()->addMonth(6);

        } else {
            $input['code'] = build_order_no('C');
        }
        $coupon = $coupon->create($input);
        if ($coupon) {
            $decrementDiscount = ElDiscount::where(['id' => $coupon_id])->decrement('usage_limit');
            $incrementDisount = ElDiscount::where(['id' => $coupon_id])->increment('used');
            if ($decrementDiscount && $incrementDisount) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function getExportDataPaginate($discount_id, $limit)
    {
        $coupons = ElDiscountCoupon::where('discount_id', $discount_id)->where('code', 'like', '%CT%')->paginate($limit);

        $lastPage = $coupons->lastPage();
        $data = [];
        foreach ($coupons as $key => $item) {
            $data[$key][] = $item->code;
        }
        return ['data' => $data, 'lastPage' => $lastPage];

    }


    /**
     * 获取优惠券领取记录数据
     * @param $where
     * @param int $limit
     * @param array $time
     * @return mixed
     */
    public function getCouponsPaginated($where, $limit = 50, $time = [])
    {
        $query = $this->model->orderBy('created_at', 'desc');

        if (count($where) > 0) {
            foreach ($where as $key => $value) {
                if ($key!='mobile') {
                    if (is_array($value)) {
                        list($operate, $va) = $value;
                        $query = $query->where($key, $operate, $va);
                    } else {
                        $query = $query->where($key, $value);
                    }
                }
            }
        }

        if (count($time) > 0) {
            foreach ($time as $key => $value) {
                if (is_array($value)) {
                    list($operate, $va) = $value;
                    $query = $query->where($key, $operate, $va);
                } else {
                    $query = $query->where($key, $value);
                }
            }
        }      

        $query->whereHas('user', function ($query) use ($where) {
            $query->where(function ($query) use ($where) {
                if (isset($where['mobile'])) {
                    list($operate, $va) = $where['mobile'];
                    $query->where('el_user.mobile', $operate, $va);
                }
            });
        });

        $query = $query->with('order')->with('user');

        if ($limit == 0) {
            return $query->all();
        } else {
            return $query->paginate($limit);
        }
    }

}
