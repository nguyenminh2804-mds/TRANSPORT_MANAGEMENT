const API_BASE_URL =
    'http://localhost:19160/TRANSPORT_MANAGEMENT/backend/public/index.php';


const form =
    document.getElementById('forgotPasswordForm');

const message =
    document.getElementById('forgotMessage');


form.addEventListener(
    'submit',
    async function (event) {

        event.preventDefault();

        message.innerHTML = '';


        const username =
            document.getElementById('username')
                .value.trim();

        const fullName =
            document.getElementById('fullName')
                .value.trim();

        const newPassword =
            document.getElementById('newPassword')
                .value;

        const confirmPassword =
            document.getElementById('confirmPassword')
                .value;


        if (newPassword !== confirmPassword) {

            message.innerHTML = `
                <div class="alert alert-danger">
                    Mật khẩu xác nhận không khớp.
                </div>
            `;

            return;
        }


        try {

            const response =
                await fetch(
                    `${API_BASE_URL}?route=/api/forgot-password`,
                    {
                        method: 'POST',

                        headers: {
                            'Content-Type':
                                'application/json'
                        },

                        credentials: 'include',

                        body: JSON.stringify({

                            username: username,

                            full_name: fullName,

                            new_password:
                                newPassword,

                            confirm_password:
                                confirmPassword

                        })
                    }
                );


            const result =
                await response.json();


            if (!result.success) {

                message.innerHTML = `
                    <div class="alert alert-danger">
                        ${result.message}
                    </div>
                `;

                return;
            }


            message.innerHTML = `
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
                'Forgot password error:',
                error
            );


            message.innerHTML = `
                <div class="alert alert-danger">
                    Không thể kết nối đến máy chủ.
                </div>
            `;

        }

    }
);