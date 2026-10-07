const API_BASE_URL =
    'http://localhost:19160/TRANSPORT_MANAGEMENT/backend/public/index.php';


const registerForm =
    document.getElementById('registerForm');

const registerMessage =
    document.getElementById('registerMessage');


registerForm.addEventListener(
    'submit',
    async function (event) {

        event.preventDefault();

        registerMessage.innerHTML = '';


        const username =
            document.getElementById('username')
                .value.trim();

        const fullName =
            document.getElementById('fullName')
                .value.trim();

        const phone =
            document.getElementById('phone')
                .value.trim();

        const email =
            document.getElementById('email')
                .value.trim();

        const address =
            document.getElementById('address')
                .value.trim();

        const password =
            document.getElementById('password')
                .value;

        const confirmPassword =
            document.getElementById('confirmPassword')
                .value;


        if (password !== confirmPassword) {

            registerMessage.innerHTML = `
                <div class="alert alert-danger">
                    Mật khẩu xác nhận không khớp.
                </div>
            `;

            return;
        }


        try {

            const response =
                await fetch(
                    `${API_BASE_URL}?route=/api/customer/register`,
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json'
                        },

                        credentials: 'include',

                        body: JSON.stringify({

                            username: username,

                            password: password,

                            confirm_password:
                                confirmPassword,

                            full_name: fullName,

                            phone: phone,

                            email: email,

                            address: address

                        })
                    }
                );


            const result =
                await response.json();


            if (!result.success) {

                registerMessage.innerHTML = `
                    <div class="alert alert-danger">
                        ${result.message}
                    </div>
                `;

                return;
            }


            registerMessage.innerHTML = `
                <div class="alert alert-success">
                    ${result.message}
                </div>
            `;


            setTimeout(
                function () {

                    window.location.href =
                        'login.html';

                },
                1200
            );


        }
        catch (error) {

            console.error(
                'Register error:',
                error
            );


            registerMessage.innerHTML = `
                <div class="alert alert-danger">
                    Không thể kết nối đến máy chủ.
                </div>
            `;

        }

    }
);