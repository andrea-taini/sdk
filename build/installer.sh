#!/bin/bash
echo ""
echo "Spryker SDK Installer"
echo ""

# Create destination folder
DESTINATION=$1
DESTINATION=${DESTINATION:-/opt/spryker-sdk}


mkdir -p "${DESTINATION}" &> /dev/null

if [ ! -d "${DESTINATION}" ]; then
    echo "Could not create ${DESTINATION}, please use a different directory to install the Spryker SDK into:"
    echo "./installer.sh /your/writeable/directory"
    exit 1
fi

# Find __ARCHIVE__ maker, read archive content and decompress it
ARCHIVE=$(awk '/^__ARCHIVE__/ {print NR + 1; exit 0; }' "${0}")
tail -n+"${ARCHIVE}" "${0}" | tar xpJ -C "${DESTINATION}"

${DESTINATION}/bin/spryker-sdk.sh sdk:init:sdk
${DESTINATION}/bin/spryker-sdk.sh sdk:update:all

echo ""
echo "Installation complete."
echo "To use the spryker sdk execute: "
echo "echo \"alias spryker-sdk='${DESTINATION}/bin/spryker-sdk.sh'\" >> ~/.bashrc && source ~/.bashrc OR echo \"alias spryker-sdk='</path/to/install/sdk/in>/bin/spryker-sdk.sh'\" >> ~/.zshrc  && source ~/.zshrc if you use zsh"
echo ""

# Exit from the script with success (0)
exit 0

__ARCHIVE__
�7zXZ  �ִF !   t/��'��] 1J��7:Q�!:���e�Z=`��L��V	�a�/��lP�[��� �/og�S	+�  DyWPi��ϲ祉OYڪE����y���k�7�I�-�%P�Q�^o3�z4e�cnS�{!M��w��EV߁說��t������}�����J�_ ��A|�[asZ�+�+&J��:@���<,�Y��k\�0R��1���f?�w��>�W]W�sK�::��}�G������c�\��:�ɖ��ӽc�o��p�����6&�;�ˤG�\�4�����q�1�=�	�Lt"����U0�����S�cɋV�)P��[��vR�E9tWb�i2B%����v�z/��YP�(���?^���ҥ��D����iW�`�8Ñ��޵'Zp��䴽#{]4��a��9a<:�3Ǔ�r�u��	<�j�
���\�Ńl��בBr�\�p�N�V�>��j����r��67�%�*Y\��>2��O���B�\�b���8R��Hö~����N('ZK�4R��+���y�KU}����\�O�8�g���9�D�{39�|��N����pE�b�B�~�{;S��D6�� ��PMj_�Ir������y�6��l�p`��<�%��Zn�l��	,E�
��4mq�.�B�	��ۉ�����3
j(}��9FN����� ��WEC/��jHE��+������������Ջ�_�X�_GU�8Y��tS� ��xG6���,�\�b�-���NKr�u[G�̆�j�x���s���x���+:�[[y0̅�&���ё�hq�8�w�����9��i淙g��X[�(���j� E�Y�0\@����R!��Y\��VW��o+�����~�����}y5�z>׮�M��i�>�~'.�\�Y���u�a�<i��nP22ɧ�"�U�v�[�#f�uq��(����9�S�c~����^3��V�;l<��)����n穸�I�@�tˬE�/��?���uw#(�Httl�IY ���X���&��!=�h7�Rf�}����u�S�8Q��I8[e�i{uf@��'�'p�&���*"��1.�k��q��BP=d�p��%fm6�wYī[K�P��� v��6T�����R�hD%~�8$���R�W������,_�E�z{1!�� �@��t��o��оvS��	V����;#i_b&w�Si��\�F0�I�Ű��0��<,�5�|I/)'������؏�*��xl/T�� �<+�͠����*s���m@��זB_.��wX��H;�ͨ�٬xYS�1�<��|�?Al1X���{�B��*�$	��>Z��ԑ���׆,88�Nc���q��<%�������7D����p�D!�}��řT1�[���l�m�@qL��-�v��(���j�n�D����  ���/MY� ��P  ���ȱ�g�    YZ