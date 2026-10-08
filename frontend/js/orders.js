/*
=========================================================
ORDERS.JS

UC-DH-01 - Khách hàng tạo đơn hàng
=========================================================
*/


const API_BASE =
    '/TRANSPORT_MANAGEMENT/backend/public/index.php';



/* =========================================================
   API REQUEST
========================================================= */

async function apiRequest(
    route,
    options = {}
) {

    const url = new URL(
        API_BASE,
        window.location.origin
    );


    url.searchParams.set(
        'route',
        route
    );


    let response;


    try {

        response = await fetch(
            url.href,
            {
                ...options,

                credentials:
                    'same-origin',

                cache:
                    'no-store'
            }
        );

    } catch (error) {

        console.error(
            'Fetch error:',
            error
        );


        throw new Error(
            'Không thể kết nối tới máy chủ. Hãy kiểm tra Apache/XAMPP.'
        );
    }


    let result;


    try {

        const text =
            await response.text();


        console.log(
            'HTTP',
            response.status,
            route
        );


        console.log(
            'Raw response:',
            text
        );


        result =
            JSON.parse(text);

    } catch (error) {

        console.error(
            'JSON parse error:',
            error
        );


        throw new Error(
            `Máy chủ trả HTTP ${response.status} nhưng không trả JSON.`
        );
    }


    console.log(
        'API:',
        route,
        result
    );


    if (
        response.status === 401
    ) {

        window.location.href =
            'login.html';


        throw new Error(
            'Phiên đăng nhập đã hết hạn.'
        );
    }


    if (
        !response.ok ||
        !result ||
        result.success !== true
    ) {

        throw new Error(
            result?.message ||
            `Yêu cầu thất bại - HTTP ${response.status}`
        );
    }


    return result;
}



/* =========================================================
   HELPER
========================================================= */

function escapeHtml(value) {

    return String(
        value ?? ''
    )

        .replace(
            /&/g,
            '&amp;'
        )

        .replace(
            /</g,
            '&lt;'
        )

        .replace(
            />/g,
            '&gt;'
        )

        .replace(
            /"/g,
            '&quot;'
        )

        .replace(
            /'/g,
            '&#039;'
        );
}



function formatMoney(value) {

    const number =
        Number(value);


    if (
        !Number.isFinite(number)
    ) {

        return '0 ₫';
    }


    return (
        number.toLocaleString('vi-VN')
        + ' ₫'
    );
}



function formatDate(value) {

    if (!value) {

        return '-';
    }


    const date =
        new Date(
            String(value)
                .replace(
                    ' ',
                    'T'
                )
        );


    if (
        Number.isNaN(
            date.getTime()
        )
    ) {

        return escapeHtml(
            value
        );
    }


    return date.toLocaleDateString(
        'vi-VN'
    );
}



/*
    Ghép địa chỉ:

    Địa chỉ chi tiết
    → Phường/Xã
    → Quận/Huyện
    → Tỉnh/Thành phố
*/

function buildAddress(
    city,
    district,
    ward,
    detail
) {

    return [

        detail,

        ward,

        district,

        city

    ]

        .map(
            value =>
                String(
                    value || ''
                ).trim()
        )

        .filter(Boolean)

        .join(', ');
}



/* =========================================================
   ĐỊA CHỈ
========================================================= */


/*
    Dữ liệu địa chỉ mẫu cho giao diện.

    Luồng:

    Tỉnh/Thành phố
        ↓
    Quận/Huyện
        ↓
    Phường/Xã

    Không cho nhập tay tỉnh/quận/phường.
*/

const ADDRESS_DATA = {

    "Hà Nội": {

        "Ba Đình": [
            "Phúc Xá",
            "Trúc Bạch",
            "Vĩnh Phúc"
        ],

        "Cầu Giấy": [
            "Dịch Vọng",
            "Mai Dịch",
            "Nghĩa Đô"
        ],

        "Nam Từ Liêm": [
            "Cầu Diễn",
            "Mỹ Đình 1",
            "Mỹ Đình 2"
        ],

        "Bắc Từ Liêm": [
            "Phú Diễn",
            "Xuân Đỉnh",
            "Tây Tựu"
        ],

        "Thanh Xuân": [
            "Khương Đình",
            "Nhân Chính",
            "Thanh Xuân Trung"
        ],

        "Hà Đông": [
            "La Khê",
            "Mộ Lao",
            "Yên Nghĩa"
        ]

    },


    "Hải Phòng": {

        "Hồng Bàng": [
            "Sở Dầu",
            "Thượng Lý",
            "Hùng Vương"
        ],

        "Lê Chân": [
            "An Biên",
            "Dư Hàng",
            "Kênh Dương"
        ],

        "Ngô Quyền": [
            "Cầu Đất",
            "Lạc Viên",
            "Máy Tơ"
        ]

    },


    "Bắc Ninh": {

        "Từ Sơn": [
            "Đình Bảng",
            "Đông Ngàn",
            "Đồng Kỵ"
        ],

        "Yên Phong": [
            "Đông Tiến",
            "Long Châu",
            "Yên Trung"
        ],

        "Tiên Du": [
            "Lim",
            "Nội Duệ",
            "Phú Lâm"
        ]

    },


    "Hưng Yên": {

        "Văn Lâm": [
            "Như Quỳnh",
            "Lạc Đạo",
            "Trưng Trắc"
        ],

        "Mỹ Hào": [
            "Bần Yên Nhân",
            "Dị Sử",
            "Phùng Chí Kiên"
        ],

        "Văn Giang": [
            "Xuân Quan",
            "Cửu Cao",
            "Long Hưng"
        ]

    },


    "Ninh Bình": {

        "Hoa Lư": [
            "Ninh Mỹ",
            "Ninh Giang",
            "Ninh Khang"
        ],

        "Gia Viễn": [
            "Gia Vân",
            "Gia Phương",
            "Gia Sinh"
        ],

        "Yên Khánh": [
            "Khánh Thiện",
            "Khánh Hòa",
            "Khánh Nhạc"
        ]

    }

};



/* =========================================================
   SELECT ĐỊA CHỈ
========================================================= */

function resetSelect(
    select,
    placeholder
) {

    if (!select) {

        return;
    }


    select.innerHTML = '';


    const option =
        document.createElement(
            'option'
        );


    option.value = '';


    option.textContent =
        placeholder;


    select.appendChild(
        option
    );


    select.value = '';


    select.disabled = true;
}



function fillSelect(
    select,
    values,
    placeholder
) {

    if (!select) {

        return;
    }


    resetSelect(
        select,
        placeholder
    );


    values.forEach(
        value => {

            const option =
                document.createElement(
                    'option'
                );


            option.value =
                value;


            option.textContent =
                value;


            select.appendChild(
                option
            );

        }
    );


    select.disabled =
        values.length === 0;
}



function setupAddressSelect(
    cityId,
    districtId,
    wardId
) {

    const city =
        document.getElementById(
            cityId
        );


    const district =
        document.getElementById(
            districtId
        );


    const ward =
        document.getElementById(
            wardId
        );


    if (
        !city ||
        !district ||
        !ward
    ) {

        console.warn(
            'Không tìm thấy select địa chỉ:',
            cityId,
            districtId,
            wardId
        );


        return;
    }



    /* =====================================================
       CHỌN TỈNH
    ===================================================== */

    city.addEventListener(
        'change',
        () => {

            clearFieldError(
                city
            );


            resetSelect(
                district,
                '-- Chọn quận / huyện --'
            );


            resetSelect(
                ward,
                '-- Chọn phường / xã --'
            );


            const cityData =
                ADDRESS_DATA[
                    city.value
                ];


            if (!cityData) {

                showFieldError(
                    city,
                    'Vui lòng chọn tỉnh / thành phố.'
                );

                return;
            }


            fillSelect(
                district,
                Object.keys(
                    cityData
                ),
                '-- Chọn quận / huyện --'
            );

        }
    );



    /* =====================================================
       CHỌN QUẬN / HUYỆN
    ===================================================== */

    district.addEventListener(
        'change',
        () => {

            clearFieldError(
                district
            );


            resetSelect(
                ward,
                '-- Chọn phường / xã --'
            );


            const cityData =
                ADDRESS_DATA[
                    city.value
                ];


            if (
                !cityData ||
                !cityData[
                    district.value
                ]
            ) {

                showFieldError(
                    district,
                    'Vui lòng chọn quận / huyện.'
                );

                return;
            }


            fillSelect(
                ward,
                cityData[
                    district.value
                ],
                '-- Chọn phường / xã --'
            );

        }
    );



    /* =====================================================
       CHỌN PHƯỜNG / XÃ
    ===================================================== */

    ward.addEventListener(
        'change',
        () => {

            if (!ward.value) {

                showFieldError(
                    ward,
                    'Vui lòng chọn phường / xã.'
                );

                return;
            }


            clearFieldError(
                ward
            );

        }
    );

}



/* =========================================================
   VALIDATION ĐỊA CHỈ CHI TIẾT
========================================================= */

function showFieldError(
    element,
    message
) {

    if (!element) {

        return;
    }


    element.classList.add(
        'input-error'
    );


    element.classList.remove(
        'input-success'
    );


    const errorElement =
        document.getElementById(
            element.id + 'Error'
        );


    if (errorElement) {

        errorElement.textContent =
            '⚠ ' + message;


        errorElement.classList.add(
            'show'
        );
    }
}



function clearFieldError(
    element
) {

    if (!element) {

        return;
    }


    element.classList.remove(
        'input-error'
    );


    const errorElement =
        document.getElementById(
            element.id + 'Error'
        );


    if (errorElement) {

        errorElement.textContent =
            '';


        errorElement.classList.remove(
            'show'
        );
    }
}



function validateDetailAddress(
    input
) {

    if (!input) {

        return false;
    }


    const value =
        input.value.trim();


    /* Không được bỏ trống */

    if (!value) {

        showFieldError(
            input,
            'Vui lòng nhập địa chỉ chi tiết.'
        );

        return false;
    }


    /* Tối thiểu 5 ký tự */

    if (value.length < 5) {

        showFieldError(
            input,
            'Địa chỉ chi tiết phải có ít nhất 5 ký tự.'
        );

        return false;
    }


    /* Không vượt quá 255 ký tự */

    if (value.length > 255) {

        showFieldError(
            input,
            'Địa chỉ chi tiết không được vượt quá 255 ký tự.'
        );

        return false;
    }


    /*
     * Không cho ký tự đặc biệt bất thường.
     *
     * Cho phép:
     * - chữ
     * - số
     * - khoảng trắng
     * - dấu phẩy
     * - dấu chấm
     * - dấu gạch ngang
     * - dấu /
     */

    const validPattern =
        /^[A-Za-zÀ-ỹ0-9Đđ\s,./-]+$/;


    if (
        !validPattern.test(value)
    ) {

        showFieldError(
            input,
            'Địa chỉ chứa ký tự không hợp lệ.'
        );

        return false;
    }


    clearFieldError(
        input
    );


    input.classList.add(
        'input-success'
    );


    return true;
}



function setupDetailValidation(
    inputId
) {

    const input =
        document.getElementById(
            inputId
        );


    if (!input) {

        return;
    }


    input.addEventListener(
        'input',
        () => {

            /*
             * Khi người dùng đang sửa,
             * bỏ trạng thái xanh trước.
             */

            input.classList.remove(
                'input-success'
            );


            /*
             * Nếu đang có lỗi,
             * kiểm tra lại ngay.
             */

            const errorElement =
                document.getElementById(
                    input.id + 'Error'
                );


            if (
                errorElement &&
                errorElement.classList.contains(
                    'show'
                )
            ) {

                validateDetailAddress(
                    input
                );
            }

        }
    );


    input.addEventListener(
        'blur',
        () => {

            validateDetailAddress(
                input
            );

        }
    );

}



/* =========================================================
   USER
========================================================= */

async function loadCurrentUser() {

    const welcomeUser =
        document.getElementById(
            'welcomeUser'
        );


    if (!welcomeUser) {

        console.warn(
            'Không tìm thấy #welcomeUser'
        );
    }


    try {

        const result =
            await apiRequest(
                '/api/me'
            );


        const user =
            result.data || {};


        if (welcomeUser) {

            welcomeUser.textContent =
                user.full_name ||
                user.username ||
                'Xin chào';
        }

    } catch (error) {

        console.error(
            'Không lấy được user:',
            error
        );
    }
}



/* =========================================================
   STATUS
========================================================= */

function getStatusInfo(
    status
) {

    const map = {

        PENDING: {

            label:
                'Chờ xử lý',

            className:
                'pending',

            icon:
                'fa-solid fa-clock'

        },


        CONFIRMED: {

            label:
                'Đã xác nhận',

            className:
                'confirmed',

            icon:
                'fa-solid fa-circle-check'

        },


        PICKING_UP: {

            label:
                'Đang lấy hàng',

            className:
                'picking',

            icon:
                'fa-solid fa-box'

        },


        IN_TRANSIT: {

            label:
                'Đang vận chuyển',

            className:
                'transit',

            icon:
                'fa-solid fa-truck'

        },


        DELIVERED: {

            label:
                'Đã giao thành công',

            className:
                'delivered',

            icon:
                'fa-solid fa-check'

        },


        CANCELLED: {

            label:
                'Đã hủy',

            className:
                'cancelled',

            icon:
                'fa-solid fa-xmark'

        }

    };


    return (
        map[status] ||

        {

            label:
                status ||
                'Không xác định',

            className:
                'unknown',

            icon:
                'fa-solid fa-circle-question'

        }
    );
}



/* =========================================================
   PAYMENT
========================================================= */

function getPaymentInfo(
    status
) {

    if (
        status === 'PAID'
    ) {

        return {

            label:
                'Đã thanh toán',

            className:
                'paid'

        };
    }


    return {

        label:
            'Chưa thanh toán',

        className:
            'unpaid'

    };
}



/* =========================================================
   SUMMARY
========================================================= */

function updateCreateSummary() {

    const products =
        document.querySelectorAll(
            '.product-item'
        );


    let totalWeight = 0;


    products.forEach(
        product => {

            const input =
                product.querySelector(
                    '.product-weight'
                );


            const weight =
                Number(
                    input?.value || 0
                );


            if (
                Number.isFinite(
                    weight
                ) &&
                weight > 0
            ) {

                totalWeight +=
                    weight;
            }

        }
    );


    const countElement =
        document.getElementById(
            'summaryProductCount'
        );


    const weightElement =
        document.getElementById(
            'summaryWeight'
        );


    const feeElement =
        document.getElementById(
            'summaryShippingFee'
        );


    if (countElement) {

        countElement.textContent =
            products.length;
    }


    if (weightElement) {

        weightElement.textContent =
            `${totalWeight.toFixed(2)} kg`;
    }


    /*
     * Báo cáo không quy định
     * công thức tính phí vận chuyển.
     */

    if (feeElement) {

        feeElement.textContent =
            'Theo hệ thống';
    }


    return totalWeight;
}



/* =========================================================
   PRODUCTS
========================================================= */

let productIndex = 1;



const PRODUCT_OPTIONS = [

    'Điện thoại',

    'Máy tính',

    'Laptop',

    'Máy tính bảng',

    'Quần áo',

    'Giày dép',

    'Đồ gia dụng',

    'Thực phẩm',

    'Đồ điện tử',

    'Hàng hóa khác'

];



function createProductOptions() {

    return PRODUCT_OPTIONS

        .map(
            product => `

                <option
                    value="${escapeHtml(product)}"
                >
                    ${escapeHtml(product)}
                </option>

            `
        )

        .join('');
}



function updateProductNumbers() {

    const products =
        document.querySelectorAll(
            '.product-item'
        );


    products.forEach(
        (product, index) => {

            const number =
                index + 1;


            product.dataset.index =
                number;


            const title =
                product.querySelector(
                    '.product-number'
                );


            if (title) {

                title.textContent =
                    `Sản phẩm ${number}`;
            }


            const removeButton =
                product.querySelector(
                    '.remove-product'
                );


            if (removeButton) {

                removeButton.style.display =
                    products.length === 1
                        ? 'none'
                        : 'inline-flex';
            }

        }
    );
}



function bindProductEvents() {

    document
        .querySelectorAll(
            '.product-weight, .product-quantity, .product-name'
        )

        .forEach(
            input => {

                input.addEventListener(
                    'input',
                    updateCreateSummary
                );


                input.addEventListener(
                    'change',
                    updateCreateSummary
                );

            }
        );


    document
        .querySelectorAll(
            '.remove-product'
        )

        .forEach(
            button => {

                button.addEventListener(
                    'click',
                    () => {

                        const product =
                            button.closest(
                                '.product-item'
                            );


                        if (!product) {

                            return;
                        }


                        const count =
                            document.querySelectorAll(
                                '.product-item'
                            ).length;


                        if (
                            count <= 1
                        ) {

                            return;
                        }


                        product.remove();


                        updateProductNumbers();


                        updateCreateSummary();

                    }
                );

            }
        );
}



/* =========================================================
   ADD PRODUCT
========================================================= */

function addProduct() {

    productIndex++;


    const list =
        document.getElementById(
            'productList'
        );


    if (!list) {

        return;
    }


    const product =
        document.createElement(
            'div'
        );


    product.className =
        'product-item';


    product.dataset.index =
        productIndex;


    product.innerHTML = `

        <div class="product-item-top">

            <strong class="product-number">

                Sản phẩm ${productIndex}

            </strong>


            <button
                type="button"
                class="remove-product"
                title="Xóa sản phẩm"
            >

                <i class="fa-solid fa-trash"></i>

            </button>

        </div>


        <div class="form-grid product-grid">


            <div class="form-group">

                <label>

                    Tên sản phẩm

                    <span>*</span>

                </label>


                <select
                    class="product-name"
                    required
                >

                    <option value="">
                        -- Chọn sản phẩm --
                    </option>

                    ${createProductOptions()}

                </select>

            </div>



            <div class="form-group">

                <label>

                    Số lượng

                    <span>*</span>

                </label>


                <input
                    type="number"
                    class="product-quantity"
                    min="1"
                    max="999999"
                    step="1"
                    value="1"
                    required
                >

            </div>



            <div class="form-group">

                <label>

                    Khối lượng (kg)

                    <span>*</span>

                </label>


                <input
                    type="number"
                    class="product-weight"
                    min="0.01"
                    max="999999"
                    step="0.01"
                    value="1"
                    required
                >

            </div>

        </div>

    `;


    list.appendChild(
        product
    );


    bindProductEvents();


    updateProductNumbers();


    updateCreateSummary();
}



/* =========================================================
   RESET PRODUCTS
========================================================= */

function resetProducts() {

    const list =
        document.getElementById(
            'productList'
        );


    if (!list) {

        return;
    }


    list.innerHTML = `

        <div
            class="product-item"
            data-index="1"
        >

            <div class="product-item-top">

                <strong class="product-number">
                    Sản phẩm 1
                </strong>

            </div>


            <div class="form-grid product-grid">


                <div class="form-group">

                    <label>

                        Tên sản phẩm

                        <span>*</span>

                    </label>


                    <select
                        class="product-name"
                        required
                    >

                        <option value="">
                            -- Chọn sản phẩm --
                        </option>

                        ${createProductOptions()}

                    </select>

                </div>



                <div class="form-group">

                    <label>

                        Số lượng

                        <span>*</span>

                    </label>


                    <input
                        type="number"
                        class="product-quantity"
                        min="1"
                        max="999999"
                        step="1"
                        value="1"
                        required
                    >

                </div>



                <div class="form-group">

                    <label>

                        Khối lượng (kg)

                        <span>*</span>

                    </label>


                    <input
                        type="number"
                        class="product-weight"
                        min="0.01"
                        max="999999"
                        step="0.01"
                        value="1"
                        required
                    >

                </div>

            </div>

        </div>

    `;


    productIndex = 1;


    bindProductEvents();


    updateProductNumbers();


    updateCreateSummary();
}



/* =========================================================
   RESET ADDRESS
========================================================= */

function resetAddressSelects() {

    const pickupCity =
        document.getElementById(
            'pickupCity'
        );


    const pickupDistrict =
        document.getElementById(
            'pickupDistrict'
        );


    const pickupWard =
        document.getElementById(
            'pickupWard'
        );


    const pickupDetail =
        document.getElementById(
            'pickupDetail'
        );


    const deliveryCity =
        document.getElementById(
            'deliveryCity'
        );


    const deliveryDistrict =
        document.getElementById(
            'deliveryDistrict'
        );


    const deliveryWard =
        document.getElementById(
            'deliveryWard'
        );


    const deliveryDetail =
        document.getElementById(
            'deliveryDetail'
        );


    if (pickupCity) {

        pickupCity.value = '';

    }


    if (deliveryCity) {

        deliveryCity.value = '';

    }


    resetSelect(
        pickupDistrict,
        '-- Chọn quận / huyện --'
    );


    resetSelect(
        pickupWard,
        '-- Chọn phường / xã --'
    );


    resetSelect(
        deliveryDistrict,
        '-- Chọn quận / huyện --'
    );


    resetSelect(
        deliveryWard,
        '-- Chọn phường / xã --'
    );


    if (pickupDetail) {

        pickupDetail.value = '';

        clearFieldError(
            pickupDetail
        );
    }


    if (deliveryDetail) {

        deliveryDetail.value = '';

        clearFieldError(
            deliveryDetail
        );
    }

}



/* =========================================================
   VALIDATE FORM
========================================================= */

function validateOrderForm() {

    let isValid = true;


    /* =====================================================
       PICKUP CITY
    ===================================================== */

    const pickupCity =
        document.getElementById(
            'pickupCity'
        );


    const pickupDistrict =
        document.getElementById(
            'pickupDistrict'
        );


    const pickupWard =
        document.getElementById(
            'pickupWard'
        );


    const pickupDetail =
        document.getElementById(
            'pickupDetail'
        );


    if (
        !pickupCity?.value
    ) {

        showFieldError(
            pickupCity,
            'Vui lòng chọn tỉnh / thành phố lấy hàng.'
        );

        isValid = false;

    } else {

        clearFieldError(
            pickupCity
        );
    }


    if (
        !pickupDistrict?.value
    ) {

        showFieldError(
            pickupDistrict,
            'Vui lòng chọn quận / huyện lấy hàng.'
        );

        isValid = false;

    } else {

        clearFieldError(
            pickupDistrict
        );
    }


    if (
        !pickupWard?.value
    ) {

        showFieldError(
            pickupWard,
            'Vui lòng chọn phường / xã lấy hàng.'
        );

        isValid = false;

    } else {

        clearFieldError(
            pickupWard
        );
    }


    if (
        !validateDetailAddress(
            pickupDetail
        )
    ) {

        isValid = false;
    }



    /* =====================================================
       DELIVERY
    ===================================================== */

    const deliveryCity =
        document.getElementById(
            'deliveryCity'
        );


    const deliveryDistrict =
        document.getElementById(
            'deliveryDistrict'
        );


    const deliveryWard =
        document.getElementById(
            'deliveryWard'
        );


    const deliveryDetail =
        document.getElementById(
            'deliveryDetail'
        );


    if (
        !deliveryCity?.value
    ) {

        showFieldError(
            deliveryCity,
            'Vui lòng chọn tỉnh / thành phố giao hàng.'
        );

        isValid = false;

    } else {

        clearFieldError(
            deliveryCity
        );
    }


    if (
        !deliveryDistrict?.value
    ) {

        showFieldError(
            deliveryDistrict,
            'Vui lòng chọn quận / huyện giao hàng.'
        );

        isValid = false;

    } else {

        clearFieldError(
            deliveryDistrict
        );
    }


    if (
        !deliveryWard?.value
    ) {

        showFieldError(
            deliveryWard,
            'Vui lòng chọn phường / xã giao hàng.'
        );

        isValid = false;

    } else {

        clearFieldError(
            deliveryWard
        );
    }


    if (
        !validateDetailAddress(
            deliveryDetail
        )
    ) {

        isValid = false;
    }


    return isValid;
}



/* =========================================================
   CREATE ORDER - UC-DH-01
========================================================= */

async function createOrder(
    event
) {

    event.preventDefault();


    const form =
        document.getElementById(
            'createOrderForm'
        );


    if (!form) {

        return;
    }


    /*
     * Kiểm tra thông tin địa chỉ.
     */

    if (
        !validateOrderForm()
    ) {

        /*
         * Đưa người dùng đến
         * phần đầu tiên bị lỗi.
         */

        const firstError =
            document.querySelector(
                '.input-error'
            );


        if (firstError) {

            firstError.focus();

            firstError.scrollIntoView({
                behavior:
                    'smooth',

                block:
                    'center'
            });
        }


        return;
    }



    /* =====================================================
       LẤY ĐỊA CHỈ
    ===================================================== */

    const pickupCity =
        document.getElementById(
            'pickupCity'
        )?.value || '';


    const pickupDistrict =
        document.getElementById(
            'pickupDistrict'
        )?.value || '';


    const pickupWard =
        document.getElementById(
            'pickupWard'
        )?.value || '';


    const pickupDetail =
        document.getElementById(
            'pickupDetail'
        )?.value.trim() || '';



    const deliveryCity =
        document.getElementById(
            'deliveryCity'
        )?.value || '';


    const deliveryDistrict =
        document.getElementById(
            'deliveryDistrict'
        )?.value || '';


    const deliveryWard =
        document.getElementById(
            'deliveryWard'
        )?.value || '';


    const deliveryDetail =
        document.getElementById(
            'deliveryDetail'
        )?.value.trim() || '';



    /* =====================================================
       GHÉP ĐỊA CHỈ
    ===================================================== */

    const pickupAddress =
        buildAddress(
            pickupCity,
            pickupDistrict,
            pickupWard,
            pickupDetail
        );


    const deliveryAddress =
        buildAddress(
            deliveryCity,
            deliveryDistrict,
            deliveryWard,
            deliveryDetail
        );



    /* =====================================================
       PRODUCTS
    ===================================================== */

    const products =
        document.querySelectorAll(
            '.product-item'
        );


    if (
        products.length === 0
    ) {

        alert(
            'Vui lòng thêm ít nhất một sản phẩm.'
        );

        return;
    }


    const items = [];


    let totalWeight = 0;


    for (
        const product of products
    ) {


        const name =
            product
                .querySelector(
                    '.product-name'
                )
                ?.value
                .trim();


        const quantity =
            Number(
                product
                    .querySelector(
                        '.product-quantity'
                    )
                    ?.value
            );


        const weight =
            Number(
                product
                    .querySelector(
                        '.product-weight'
                    )
                    ?.value
            );


        if (!name) {

            alert(
                'Vui lòng chọn tên sản phẩm.'
            );

            return;
        }


        if (
            !Number.isInteger(
                quantity
            ) ||
            quantity <= 0
        ) {

            alert(
                'Số lượng phải là số nguyên lớn hơn 0.'
            );

            return;
        }


        if (
            !Number.isFinite(
                weight
            ) ||
            weight <= 0
        ) {

            alert(
                'Khối lượng phải lớn hơn 0.'
            );

            return;
        }


        totalWeight +=
            weight;


        items.push({

            product_name:
                name,

            quantity:
                quantity,

            weight:
                weight

        });

    }



    /* =====================================================
       BUTTON
    ===================================================== */

    const submitButton =
        document.getElementById(
            'submitOrderBtn'
        );


    if (submitButton) {

        submitButton.disabled =
            true;


        submitButton.innerHTML = `

            <i
                class="fa-solid fa-spinner fa-spin"
            ></i>

            Đang tạo...

        `;
    }



    /* =====================================================
       API
    ===================================================== */

    try {

        const result =
            await apiRequest(
                '/api/customer/orders',
                {

                    method:
                        'POST',


                    headers: {

                        'Content-Type':
                            'application/json'

                    },


                    body:
                        JSON.stringify({

                            pickup_address:
                                pickupAddress,


                            delivery_address:
                                deliveryAddress,


                            total_weight:
                                Number(
                                    totalWeight.toFixed(
                                        2
                                    )
                                ),


                            /*
                             * Báo cáo không quy định
                             * công thức phí vận chuyển.
                             */

                            shipping_fee:
                                0,


                            payment_status:
                                'UNPAID',


                            items:
                                items

                        })

                }
            );


        const order =
            result.data || {};


        alert(
            `Tạo đơn hàng thành công!\nMã đơn: ${
                order.order_code ||
                'Đã tạo'
            }`
        );


        /* =================================================
           RESET
        ================================================= */

        form.reset();


        resetAddressSelects();


        resetProducts();


        const section =
            document.getElementById(
                'createOrderSection'
            );


        if (section) {

            section.classList.add(
                'hidden'
            );
        }


        /*
         * Tải lại danh sách đơn
         * để đơn mới xuất hiện.
         */

        await loadOrders();


    } catch (error) {

        console.error(
            'Create order error:',
            error
        );


        alert(
            error.message ||
            'Không thể tạo đơn hàng.'
        );


    } finally {

        if (submitButton) {

            submitButton.disabled =
                false;


            submitButton.innerHTML = `

                <i
                    class="fa-solid fa-check"
                ></i>

                Tạo đơn hàng

            `;
        }

    }

}



/* =========================================================
   LOAD ORDERS
========================================================= */

async function loadOrders() {

    const tableBody =
        document.getElementById(
            'ordersTableBody'
        );


    if (!tableBody) {

        console.error(
            'Không tìm thấy #ordersTableBody'
        );

        return;
    }


    tableBody.innerHTML = `

        <tr>

            <td
                colspan="8"
                class="loading-cell"
            >

                <i
                    class="fa-solid fa-spinner fa-spin"
                ></i>

                Đang tải dữ liệu...

            </td>

        </tr>

    `;


    try {

        console.log(
            '================================'
        );


        console.log(
            'ĐANG LOAD ORDERS'
        );


        console.log(
            'API_BASE:',
            API_BASE
        );


        console.log(
            'ROUTE:',
            '/api/customer/orders'
        );


        const result =
            await apiRequest(
                '/api/customer/orders'
            );


        console.log(
            'DỮ LIỆU ORDERS:',
            result
        );


        const orders =
            Array.isArray(
                result.data
            )
                ? result.data
                : [];


        updateStatistics(
            orders
        );


        if (
            orders.length === 0
        ) {

            tableBody.innerHTML = `

                <tr>

                    <td
                        colspan="8"
                        class="empty-cell"
                    >

                        <i
                            class="fa-solid fa-box-open"
                        ></i>


                        <strong>
                            Chưa có đơn hàng
                        </strong>


                        <span>
                            Tài khoản của bạn chưa có đơn hàng nào.
                        </span>

                    </td>

                </tr>

            `;


            return;
        }


        tableBody.innerHTML =
            '';


        orders.forEach(
            order => {


                const status =
                    getStatusInfo(
                        order.status
                    );


                const payment =
                    getPaymentInfo(
                        order.payment_status
                    );


                const row =
                    document.createElement(
                        'tr'
                    );


                row.innerHTML = `

                    <td>

                        <span
                            class="order-code"
                        >

                            ${escapeHtml(
                                order.order_code ||
                                '-'
                            )}

                        </span>

                    </td>


                    <td>

                        <div
                            class="address-cell"
                        >

                            ${escapeHtml(
                                order.pickup_address ||
                                '-'
                            )}

                        </div>

                    </td>


                    <td>

                        <div
                            class="address-cell"
                        >

                            ${escapeHtml(
                                order.delivery_address ||
                                '-'
                            )}

                        </div>

                    </td>


                    <td>

                        ${Number(
                            order.total_weight ||
                            0
                        ).toFixed(2)}

                        kg

                    </td>


                    <td>

                        <strong>

                            ${formatMoney(
                                order.shipping_fee
                            )}

                        </strong>

                    </td>


                    <td>

                        <span
                            class="payment-badge ${payment.className}"
                        >

                            ${payment.label}

                        </span>

                    </td>


                    <td>

                        <span
                            class="status-badge ${status.className}"
                        >

                            <i
                                class="${status.icon}"
                            ></i>

                            ${status.label}

                        </span>

                    </td>


                    <td>

                        ${formatDate(
                            order.created_at
                        )}

                    </td>

                `;


                tableBody.appendChild(
                    row
                );

            }
        );


        console.log(
            'LOAD ORDERS THÀNH CÔNG:',
            orders.length,
            'đơn'
        );


    } catch (error) {

        console.error(
            'Load orders error:',
            error
        );


        tableBody.innerHTML = `

            <tr>

                <td
                    colspan="8"
                    class="error-cell"
                >

                    <i
                        class="fa-solid fa-triangle-exclamation"
                    ></i>


                    <strong>
                        Không thể tải danh sách đơn hàng
                    </strong>


                    <span>

                        ${escapeHtml(
                            error.message ||
                            'Vui lòng thử lại.'
                        )}

                    </span>

                </td>

            </tr>

        `;

    }

}



/* =========================================================
   STATISTICS
========================================================= */

function updateStatistics(
    orders
) {

    const total =
        orders.length;


    const pending =
        orders.filter(
            order =>
                order.status ===
                'PENDING'
        ).length;


    const shipping =
        orders.filter(
            order =>
                [
                    'CONFIRMED',
                    'PICKING_UP',
                    'IN_TRANSIT'
                ].includes(
                    order.status
                )
        ).length;


    const totalElement =
        document.getElementById(
            'totalOrders'
        );


    const shippingElement =
        document.getElementById(
            'shippingOrders'
        );


    const pendingElement =
        document.getElementById(
            'pendingOrders'
        );


    if (totalElement) {

        totalElement.textContent =
            total;
    }


    if (shippingElement) {

        shippingElement.textContent =
            shipping;
    }


    if (pendingElement) {

        pendingElement.textContent =
            pending;
    }

}



/* =========================================================
   CREATE ORDER UI
========================================================= */

function setupCreateOrderUI() {

    const createButton =
        document.getElementById(
            'createOrderBtn'
        );


    const cancelButton =
        document.getElementById(
            'cancelCreateOrderBtn'
        );


    const section =
        document.getElementById(
            'createOrderSection'
        );


    if (
        !createButton ||
        !section
    ) {

        console.warn(
            'Không tìm thấy khu vực tạo đơn.'
        );


        return;
    }



    /* =====================================================
       MỞ FORM
    ===================================================== */

    createButton.addEventListener(
        'click',
        () => {

            section.classList.remove(
                'hidden'
            );


            section.scrollIntoView({

                behavior:
                    'smooth',

                block:
                    'start'

            });

        }
    );



    /* =====================================================
       HỦY
    ===================================================== */

    if (cancelButton) {

        cancelButton.addEventListener(
            'click',
            () => {

                section.classList.add(
                    'hidden'
                );

            }
        );
    }



    /* =====================================================
       SUBMIT
    ===================================================== */

    const form =
        document.getElementById(
            'createOrderForm'
        );


    if (form) {

        form.addEventListener(
            'submit',
            createOrder
        );
    }



    /* =====================================================
       THÊM SẢN PHẨM
    ===================================================== */

    const addProductButton =
        document.getElementById(
            'addProductBtn'
        );


    if (addProductButton) {

        addProductButton.addEventListener(
            'click',
            addProduct
        );
    }



    /* =====================================================
       ĐỊA CHỈ
    ===================================================== */

    setupAddressSelect(
        'pickupCity',
        'pickupDistrict',
        'pickupWard'
    );


    setupAddressSelect(
        'deliveryCity',
        'deliveryDistrict',
        'deliveryWard'
    );



    /* =====================================================
       VALIDATION ĐỊA CHỈ CHI TIẾT
    ===================================================== */

    setupDetailValidation(
        'pickupDetail'
    );


    setupDetailValidation(
        'deliveryDetail'
    );



    /* =====================================================
       PRODUCTS
    ===================================================== */

    bindProductEvents();


    updateProductNumbers();


    updateCreateSummary();

}



/* =========================================================
   LOGOUT
========================================================= */

function setupLogout() {

    const button =
        document.getElementById(
            'logoutBtn'
        );


    if (!button) {

        console.warn(
            'Không tìm thấy #logoutBtn'
        );


        return;
    }


    button.addEventListener(
        'click',
        async () => {

            try {

                await apiRequest(
                    '/api/logout',
                    {
                        method:
                            'POST'
                    }
                );


            } catch (error) {

                console.error(
                    'Logout error:',
                    error
                );


            } finally {

                window.location.href =
                    'login.html';
            }

        }
    );

}



/* =========================================================
   START
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    async () => {


        console.log(
            '================================'
        );


        console.log(
            'ORDERS.JS ĐÃ LOAD'
        );


        console.log(
            'API_BASE:',
            API_BASE
        );


        console.log(
            '================================'
        );


        /*
         * Load danh sách đơn trước.
         */

        await loadOrders();



        /*
         * Load user.
         */

        try {

            await loadCurrentUser();

        } catch (error) {

            console.error(
                'loadCurrentUser:',
                error
            );

        }



        /*
         * Setup form.
         */

        try {

            setupCreateOrderUI();

        } catch (error) {

            console.error(
                'setupCreateOrderUI:',
                error
            );

        }



        /*
         * Logout.
         */

        try {

            setupLogout();

        } catch (error) {

            console.error(
                'setupLogout:',
                error
            );

        }

    }
);